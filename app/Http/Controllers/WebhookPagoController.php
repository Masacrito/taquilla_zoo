<?php

namespace App\Http\Controllers;

use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\PasarelaPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada de las notificaciones de pago (brief §4.4, §7).
 *
 * Responde 200 tanto si el pago se procesó como si el webhook ya venía
 * procesado: los bancos reintentan ante cualquier código distinto de 2xx, y
 * reintentar algo ya hecho solo genera ruido.
 *
 * Responde 400 únicamente cuando el webhook no es confiable (firma inválida,
 * monto que no cuadra, compra inexistente).
 */
class WebhookPagoController extends Controller
{
    public function __construct(
        private readonly PasarelaPago $pasarela,
        private readonly ConfirmarPagoService $confirmar,
    ) {
    }

    public function recibir(Request $request, string $proveedor): JsonResponse
    {
        $notificacion = $this->pasarela->procesarNotificacion($request);

        $aceptado = $this->confirmar->confirmar($notificacion, $proveedor);

        return response()->json(
            ['ok' => $aceptado],
            $aceptado ? 200 : 400,
        );
    }
}
