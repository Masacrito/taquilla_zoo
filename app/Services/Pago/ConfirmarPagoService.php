<?php

namespace App\Services\Pago;

use App\Mail\ComprobanteCompra;
use App\Models\Compra;
use App\Models\Pago;
use App\Services\Acceso\QrTokenService;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Procesa la notificación de pago (brief §4.4).
 *
 * ═══════════════════════════════════════════════════════════════════════
 *  EL QR SE EMITE SOLO AQUÍ, contra webhook verificado.
 *  Nunca desde la pantalla de retorno del cliente: esa pantalla la controla
 *  el navegador del comprador y se puede falsificar.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * IDEMPOTENTE: los bancos reenvían. La llave es pagos.referencia_externa,
 * con índice UNIQUE en la base. Si ya está procesada, se responde OK y no se
 * hace nada más — sin duplicar la compra ni reenviar el correo.
 */
class ConfirmarPagoService
{
    public function __construct(
        private readonly QrTokenService $qr,
        private readonly BitacoraService $bitacora,
    ) {
    }

    /**
     * @return bool true si el webhook se aceptó (incluye el caso "ya estaba
     *              procesado"). false solo si hay que rechazarlo.
     */
    public function confirmar(NotificacionPago $notificacion, string $proveedor): bool
    {
        if (! $notificacion->firmaValida) {
            Log::warning('Webhook de pago con firma inválida', [
                'referencia' => $notificacion->referenciaExterna,
                'proveedor'  => $proveedor,
            ]);

            return false;
        }

        // Evento legítimo que no nos corresponde. Se responde 200 para que
        // la pasarela no lo reintente, y no se escribe NADA: registrarlo como
        // pago rechazado inventaría un rechazo que nunca ocurrió y ensuciaría
        // la conciliación y los cortes.
        if ($notificacion->esIgnorable()) {
            return true;
        }

        if ($notificacion->referenciaExterna === '') {
            return false;
        }

        // ── Idempotencia: ¿ya lo procesamos? ──
        $existente = Pago::where('referencia_externa', $notificacion->referenciaExterna)->first();

        if ($existente && $existente->estado === Pago::APROBADO) {
            Log::info('Webhook de pago reenviado; ya estaba procesado', [
                'referencia' => $notificacion->referenciaExterna,
            ]);

            return true;   // 200 y nada más
        }

        $compra = $this->compraDe($notificacion, $existente);

        if (! $compra) {
            Log::warning('Webhook de pago sin compra asociada', [
                'referencia' => $notificacion->referenciaExterna,
            ]);

            return false;
        }

        // ── El monto debe coincidir con el total calculado por nosotros ──
        if ($notificacion->montoCentavos !== $compra->total_centavos) {
            Log::error('Webhook de pago con monto discrepante', [
                'referencia'      => $notificacion->referenciaExterna,
                'monto_recibido'  => $notificacion->montoCentavos,
                'monto_esperado'  => $compra->total_centavos,
                'compra'          => $compra->folio,
            ]);

            $this->registrarPago($compra, $notificacion, $proveedor, Pago::RECHAZADO);

            return false;
        }

        if (! $notificacion->aprobado()) {
            $this->registrarPago($compra, $notificacion, $proveedor, Pago::RECHAZADO);

            return true;   // notificación legítima de un pago fallido
        }

        return $this->marcarPagada($compra, $notificacion, $proveedor);
    }

    private function marcarPagada(Compra $compra, NotificacionPago $notificacion, string $proveedor): bool
    {
        try {
            DB::transaction(function () use ($compra, $notificacion, $proveedor) {

                // Relee con bloqueo: dos webhooks simultáneos no pueden pasar
                // los dos por aquí.
                $fresca = Compra::query()
                    ->when(DB::connection()->getDriverName() !== 'sqlite',
                        fn ($q) => $q->lockForUpdate())
                    ->findOrFail($compra->id);

                $this->registrarPago($fresca, $notificacion, $proveedor, Pago::APROBADO);

                // Solo una compra PENDIENTE se convierte en pagada. No se usa
                // puedeTransicionarA(): pagada → pagada existe para reagendar,
                // y aquí dejaría pasar un segundo cobro de la misma compra
                // (una compra puede tener varios cobros abiertos desde que se
                // retoma y se paga después, 2026-10-11).
                if (! $fresca->estaPendiente()) {
                    // El banco cobró algo que ya no entrega boleto: la compra
                    // ya estaba pagada con otro cobro, o expiró. El pago queda
                    // como aprobado —es lo que pasó— y a la vista para devolverlo.
                    Log::error('Pago aprobado sobre una compra que ya no está pendiente: requiere reembolso', [
                        'referencia' => $notificacion->referenciaExterna,
                        'compra'     => $fresca->folio,
                        'estado'     => $fresca->estado,
                        'monto'      => $notificacion->montoCentavos,
                    ]);

                    return;
                }

                $fresca->estado       = Compra::PAGADA;
                $fresca->qr_token     = $this->qr->generar($fresca);
                // El QR deja de servir al cerrar el día de la visita.
                $fresca->qr_expira_en = $fresca->fecha_visita->copy()->endOfDay();
                // Si estaba archivada como pendiente, ahora trae un QR: vuelve
                // a la bandeja.
                $fresca->archivada_en = null;
                $fresca->save();

                $this->bitacora->registrar('compras', BitacoraService::UPDATE, (string) $fresca->id, [
                    'folio'          => $fresca->folio,
                    'estado'         => Compra::PAGADA,
                    'total_centavos' => $fresca->total_centavos,
                    'referencia'     => $notificacion->referenciaExterna,
                ]);

                // El comprobante va EN COLA: generar el PDF y hablar con el
                // SMTP puede tardar segundos, y el banco da por fallido un
                // webhook que no responde rápido (y lo reenvía).
                //
                // afterCommit() evita encolarlo si la transacción se revierte:
                // sin eso, el worker podría tomar el job antes del COMMIT y no
                // encontrar la compra.
                Mail::to($fresca->correoDestino())
                    ->queue((new ComprobanteCompra($fresca))->afterCommit());
            });
        } catch (UniqueConstraintViolationException) {
            // Otro webhook idéntico ganó la carrera e insertó el pago primero.
            // Es exactamente el caso que el índice UNIQUE debe atrapar.
            Log::info('Webhook duplicado atrapado por la llave de idempotencia', [
                'referencia' => $notificacion->referenciaExterna,
            ]);
        }

        return true;
    }

    private function registrarPago(
        Compra $compra,
        NotificacionPago $notificacion,
        string $proveedor,
        string $estado,
    ): void {
        Pago::updateOrCreate(
            ['referencia_externa' => $notificacion->referenciaExterna],
            [
                'id_compra'       => $compra->id,
                'proveedor'       => $proveedor,
                'monto_centavos'  => $notificacion->montoCentavos,
                'estado'          => $estado,
                'autorizacion'    => $notificacion->autorizacion,
                'payload_webhook' => $notificacion->payload,
            ],
        );
    }

    /**
     * La compra sale del pago ya iniciado. Si no existe, se acepta que el
     * payload traiga el folio, para que un proveedor que no guarde metadatos
     * siga funcionando.
     */
    private function compraDe(NotificacionPago $notificacion, ?Pago $existente): ?Compra
    {
        if ($existente) {
            return $existente->compra;
        }

        $folio = $notificacion->payload['folio'] ?? null;

        return $folio ? Compra::where('folio', $folio)->first() : null;
    }
}
