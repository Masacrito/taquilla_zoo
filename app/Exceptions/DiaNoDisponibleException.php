<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La fecha de visita no está habilitada: no existe en el calendario o el día
 * está cerrado (lunes, o cierre por contingencia).
 *
 * Ya no existe el caso de "sin lugares": no hay aforo máximo.
 */
class DiaNoDisponibleException extends RuntimeException
{
    public static function paraFecha(string $fecha): self
    {
        return new self("El {$fecha} no está habilitado para visitas.");
    }
}
