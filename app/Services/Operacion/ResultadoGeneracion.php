<?php

namespace App\Services\Operacion;

/** Lo que dejó una corrida de GenerarCalendarioService. */
final readonly class ResultadoGeneracion
{
    public function __construct(
        public int $creados,
        public int $lunes,
    ) {
    }
}
