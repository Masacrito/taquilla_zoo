<?php

namespace App\Services\Venta;

/**
 * Resultado de una cotización. Inmutable a propósito: nada fuera de
 * CotizarCompraService puede fabricar o alterar un total.
 */
final class Cotizacion
{
    /**
     * @param  array<int, RenglonCotizado>  $renglones
     */
    public function __construct(
        public readonly array $renglones,
        public readonly int $totalCentavos,
        public readonly int $pasesTotal,
    ) {
    }

    public function totalFormateado(): string
    {
        return '$' . number_format($this->totalCentavos / 100, 2);
    }
}
