<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El QR no tiene pases disponibles, o la compra no está en un estado que
 * permita el acceso.
 */
class AccesoNoDisponibleException extends RuntimeException
{
}
