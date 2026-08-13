<?php

namespace App\Services\Venta;

use App\Models\Rubro;

/**
 * Un renglón ya cotizado contra el catálogo del servidor.
 *
 * `precioCentavos` y `nombre` son los valores que se congelarán en
 * compra_detalle (brief §4.3).
 */
final class RenglonCotizado
{
    public function __construct(
        public readonly Rubro $rubro,
        public readonly string $nombre,
        public readonly int $precioCentavos,
        public readonly int $cantHombre,
        public readonly int $cantMujer,
        public readonly int $cantidad,
        public readonly int $importeCentavos,
        public readonly ?int $idPais = null,
        public readonly ?int $idEstado = null,
        public readonly ?int $idMunicipio = null,
    ) {
    }
}
