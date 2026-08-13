<?php

namespace App\Services\Pago;

/**
 * Webhook ya verificado y normalizado.
 *
 * `firmaValida` la resuelve el adaptador de cada proveedor; el servicio de
 * confirmación no vuelve a interpretarla, solo la respeta.
 */
final class NotificacionPago
{
    public function __construct(
        public readonly bool $firmaValida,
        public readonly string $referenciaExterna,
        public readonly string $estado,
        public readonly int $montoCentavos,
        public readonly ?string $autorizacion,
        public readonly array $payload,
    ) {
    }
}
