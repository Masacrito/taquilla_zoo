<?php

namespace App\Services\Pago;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Resuelve la pasarela POR NOMBRE.
 *
 * Existe por dos razones, y las dos son de seguridad:
 *
 * 1. El webhook entra por /webhooks/pago/{proveedor} y tiene que procesarse
 *    con ESE adaptador. Resolverlo desde la configuración —como se hacía—
 *    funciona mientras haya una sola pasarela, pero el día que convivan dos
 *    verificaría la firma de una con el esquema de la otra.
 *
 * 2. Decide qué pasarelas pueden existir en cada entorno. La simulada no
 *    cobra nada y firma con APP_KEY si no hay secreto propio, así que fuera
 *    de los entornos de prueba no debe poder resolverse ni nombrándola en la
 *    URL.
 *
 * Cuando llegue el proveedor real: se agrega su clase al match. El binding
 * por omisión y el webhook la toman de aquí sin más cambios.
 */
final class FabricaPasarelas
{
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Instancia del proveedor, o null si no existe o no está permitido en
     * este entorno. Quien llame decide qué hacer con el null; el webhook
     * responde 404 para no confirmar qué pasarelas hay configuradas.
     */
    public function para(string $proveedor): ?PasarelaPago
    {
        return match ($proveedor) {
            PasarelaSimulada::PROVEEDOR => $this->simuladaPermitida()
                ? new PasarelaSimulada()
                : null,
            default => null,
        };
    }

    /** La pasarela de `taquilla.pago.pasarela`, para dar de alta cobros. */
    public function porOmision(): PasarelaPago
    {
        $nombre = (string) config('taquilla.pago.pasarela');

        return $this->para($nombre) ?? throw new RuntimeException(
            "Pasarela de pago «{$nombre}» no reconocida o no permitida en el entorno "
            . "«{$this->app->environment()}». Revisa PAGO_PASARELA."
        );
    }

    /**
     * Los entornos donde la pasarela simulada puede operar salen de la
     * configuración y no de una lista escrita a mano en tres archivos: es la
     * única respuesta a «dónde se permite cobrar de mentiras».
     */
    public function simuladaPermitida(): bool
    {
        return $this->app->environment(config('taquilla.pago.entornos_simulada', []));
    }
}
