<?php

namespace App\Http\Controllers;

use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\FabricaPasarelas;
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
 *
 * La pasarela se resuelve por el nombre que trae la URL, NO por la que esté
 * configurada: verificar la firma de un proveedor con el esquema de otro es
 * el tipo de error que solo se nota cuando ya se aceptó un pago falso.
 */
class WebhookPagoController extends Controller
{
    public function __construct(
        private readonly FabricaPasarelas $pasarelas,
        private readonly ConfirmarPagoService $confirmar,
    ) {
    }

    public function recibir(Request $request, string $proveedor): JsonResponse
    {
        $pasarela = $this->pasarelas->para($proveedor);

        // 404 y no 400: un proveedor desconocido —o uno que no opera en este
        // entorno, como la simulada en producción— no tiene por qué saber si
        // existe o si simplemente está apagado.
        abort_if($pasarela === null, 404);

        $notificacion = $pasarela->procesarNotificacion($request);

        $aceptado = $this->confirmar->confirmar($notificacion, $proveedor);

        return response()->json(
            ['ok' => $aceptado],
            $aceptado ? 200 : 400,
        );
    }
}
