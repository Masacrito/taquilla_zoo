<?php

namespace App\Services\Pago;

use App\Models\Compra;
use Illuminate\Http\Request;

/**
 * Contrato de la pasarela (brief §6).
 *
 * TODO el desarrollo corre contra PasarelaSimulada hasta que el banco
 * entregue credenciales. Cuando llegue el proveedor real: se agrega una
 * clase nueva que implemente esta interfaz y se cambia el binding en
 * PagoServiceProvider. Nada más se toca.
 */
interface PasarelaPago
{
    /** Da de alta el cobro y devuelve a dónde redirigir al comprador. */
    public function crearCobro(Compra $compra): CobroCreado;

    public function verificarEstado(string $referencia): EstadoPago;

    /** Valida la firma del webhook y normaliza su contenido. */
    public function procesarNotificacion(Request $request): NotificacionPago;
}
