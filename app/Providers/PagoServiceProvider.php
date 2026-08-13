<?php

namespace App\Providers;

use App\Services\Pago\PasarelaPago;
use App\Services\Pago\PasarelaSimulada;
use Illuminate\Support\ServiceProvider;

/**
 * Punto único donde se elige la pasarela (brief §6).
 *
 * Cuando el banco entregue credenciales:
 *   1. Crear App\Services\Pago\PasarelaBanorte (o la que sea) implementando
 *      PasarelaPago.
 *   2. Agregarla al match de abajo.
 *   3. Cambiar PAGO_PASARELA en el .env.
 *
 * Ningún controlador ni servicio cambia: todos dependen de la interfaz.
 */
class PagoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasarelaPago::class, function () {
            return match (config('taquilla.pago.pasarela')) {
                'simulada' => new PasarelaSimulada(),
                default    => throw new \RuntimeException(
                    'Pasarela de pago no reconocida: ' . config('taquilla.pago.pasarela')
                ),
            };
        });
    }
}
