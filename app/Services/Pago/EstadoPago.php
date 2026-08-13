<?php

namespace App\Services\Pago;

final class EstadoPago
{
    public function __construct(
        public readonly string $referenciaExterna,
        public readonly string $estado,          // iniciado | aprobado | rechazado | reembolsado
        public readonly int $montoCentavos,
        public readonly ?string $autorizacion = null,
    ) {
    }

    public function aprobado(): bool
    {
        return $this->estado === 'aprobado';
    }
}
