<?php

namespace App\Services\Pago;

/**
 * Cobro dado de alta en la pasarela: a dónde mandar al comprador y con qué
 * referencia se le va a dar seguimiento.
 */
final class CobroCreado
{
    public function __construct(
        public readonly string $referenciaExterna,
        public readonly string $urlRedireccion,
        public readonly int $montoCentavos,
    ) {
    }
}
