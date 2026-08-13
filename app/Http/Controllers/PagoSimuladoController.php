<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\PasarelaSimulada;
use Illuminate\Http\Request;

/**
 * Sustituto de la pantalla del banco, SOLO para desarrollo (brief §6, §12:
 * el proveedor real está sin definir y la Fase 4 está bloqueada).
 *
 * No simula el atajo fácil: al confirmar, arma el mismo cuerpo JSON firmado
 * con HMAC que mandaría el banco y lo hace pasar por la verificación de
 * firma real. Así el camino que se prueba en desarrollo es el mismo que
 * correrá en producción, salvo por quién cobra el dinero.
 *
 * Se apaga solo fuera de local/testing y si la pasarela configurada no es
 * la simulada.
 */
class PagoSimuladoController extends Controller
{
    public function __construct(
        private readonly PasarelaSimulada $pasarela,
        private readonly ConfirmarPagoService $confirmar,
    ) {
        abort_unless(
            app()->environment(['local', 'testing'])
                && config('taquilla.pago.pasarela') === 'simulada',
            404,
        );
    }

    public function mostrar(string $referencia)
    {
        $pago = Pago::with('compra.detalle')
            ->where('referencia_externa', $referencia)
            ->firstOrFail();

        return view('publico.pago-simulado', [
            'pago'   => $pago,
            'compra' => $pago->compra,
        ]);
    }

    public function confirmar(Request $request, string $referencia)
    {
        $pago = Pago::with('compra')->where('referencia_externa', $referencia)->firstOrFail();

        $aprobar = $request->input('resultado') === 'aprobar';

        // El mismo cuerpo que mandaría el banco.
        $cuerpo = json_encode([
            'referencia'     => $referencia,
            'estado'         => $aprobar ? 'aprobado' : 'rechazado',
            'monto_centavos' => $pago->compra->total_centavos,
            'autorizacion'   => $aprobar ? 'SIM-' . strtoupper(bin2hex(random_bytes(4))) : null,
            'folio'          => $pago->compra->folio,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Firmado con el mismo secreto y verificado por el mismo código que
        // usará el proveedor real.
        $peticion = Request::create(
            uri: route('webhooks.pago', ['proveedor' => PasarelaSimulada::PROVEEDOR]),
            method: 'POST',
            server: ['HTTP_X_FIRMA' => $this->pasarela->firmar($cuerpo), 'CONTENT_TYPE' => 'application/json'],
            content: $cuerpo,
        );

        $notificacion = $this->pasarela->procesarNotificacion($peticion);

        $this->confirmar->confirmar($notificacion, PasarelaSimulada::PROVEEDOR);

        return redirect()->route('compras.retorno', ['folio' => $pago->compra->folio]);
    }
}
