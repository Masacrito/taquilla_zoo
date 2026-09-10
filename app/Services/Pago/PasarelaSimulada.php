<?php

namespace App\Services\Pago;

use App\Models\Compra;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pasarela de desarrollo (brief §6, §12: proveedor sin definir).
 *
 * Reproduce el contrato real, incluida la FIRMA HMAC del webhook, para que
 * ConfirmarPagoService se pruebe contra el mismo mecanismo que usará el
 * banco. Lo único simulado es que aquí nadie cobra dinero.
 *
 * NO se usa en producción: el binding vive en PagoServiceProvider.
 */
class PasarelaSimulada implements PasarelaPago
{
    public const PROVEEDOR = 'simulada';

    public function crearCobro(Compra $compra): CobroCreado
    {
        $referencia = 'SIM-' . strtoupper(Str::random(16));

        return new CobroCreado(
            referenciaExterna: $referencia,
            urlRedireccion:    route('pago.simulado', ['referencia' => $referencia]),
            montoCentavos:     $compra->total_centavos,
        );
    }

    public function verificarEstado(string $referencia): EstadoPago
    {
        $pago = \App\Models\Pago::where('referencia_externa', $referencia)->first();

        return new EstadoPago(
            referenciaExterna: $referencia,
            estado:            $pago?->estado ?? 'iniciado',
            montoCentavos:     $pago?->monto_centavos ?? 0,
            autorizacion:      $pago?->autorizacion,
        );
    }

    public function procesarNotificacion(Request $request): NotificacionPago
    {
        $payload = $request->all();
        $firma   = (string) $request->header('X-Firma', '');
        $valida  = $this->firmaValida($request->getContent(), $firma);

        // Firma mala: se devuelve tal cual para que el webhook responda 400.
        if (! $valida) {
            return new NotificacionPago(
                firmaValida:       false,
                referenciaExterna: (string) ($payload['referencia'] ?? ''),
                estado:            NotificacionPago::RECHAZADO,
                montoCentavos:     (int) ($payload['monto_centavos'] ?? 0),
                autorizacion:      null,
                payload:           $payload,
            );
        }

        // Firma buena pero el cuerpo no trae el contrato: es un evento que no
        // nos toca. Antes se leía como rechazo por el `?? 'rechazado'` y
        // dejaba escrito un pago fallido inexistente.
        if (! isset($payload['referencia'], $payload['estado'])) {
            return NotificacionPago::ignorada($payload);
        }

        return new NotificacionPago(
            firmaValida:       true,
            referenciaExterna: (string) $payload['referencia'],
            estado:            (string) $payload['estado'],
            montoCentavos:     (int) ($payload['monto_centavos'] ?? 0),
            autorizacion:      $payload['autorizacion'] ?? null,
            payload:           $payload,
        );
    }

    /**
     * Mismo esquema que usan la mayoría de las pasarelas reales: HMAC-SHA256
     * del cuerpo crudo con un secreto compartido.
     */
    public function firmar(string $cuerpo): string
    {
        return hash_hmac('sha256', $cuerpo, $this->secreto());
    }

    private function firmaValida(string $cuerpo, string $firma): bool
    {
        if ($firma === '') {
            return false;
        }

        return hash_equals($this->firmar($cuerpo), $firma);
    }

    private function secreto(): string
    {
        return (string) config('taquilla.pago.secreto_webhook');
    }
}
