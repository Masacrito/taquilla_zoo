<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No queda cupo para la fecha de visita, o el día está cerrado.
 */
class AforoAgotadoException extends RuntimeException
{
    public static function paraFecha(string $fecha): self
    {
        return new self("No hay lugares disponibles para el {$fecha}.");
    }

    public static function diaNoDisponible(string $fecha): self
    {
        return new self("El {$fecha} no está habilitado para visitas.");
    }
}
