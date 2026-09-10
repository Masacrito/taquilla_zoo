<?php

namespace App\Providers;

use App\Services\Pago\FabricaPasarelas;
use App\Services\Pago\PasarelaPago;
use Illuminate\Support\ServiceProvider;

/**
 * Punto único donde se elige la pasarela (brief §6).
 *
 * Cuando el banco entregue credenciales:
 *   1. Crear App\Services\Pago\PasarelaBanorte (o la que sea) implementando
 *      PasarelaPago.
 *   2. Agregarla al match de FabricaPasarelas.
 *   3. Cambiar PAGO_PASARELA en el .env.
 *
 * Ningún controlador ni servicio cambia: todos dependen de la interfaz.
 */
class PagoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Para dar de alta cobros se usa la pasarela configurada. El webhook
        // NO pasa por aquí: resuelve por el nombre que trae la URL, con la
        // misma fábrica.
        $this->app->bind(
            PasarelaPago::class,
            fn ($app) => $app->make(FabricaPasarelas::class)->porOmision(),
        );
    }
}
