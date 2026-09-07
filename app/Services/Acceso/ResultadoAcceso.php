<?php

namespace App\Services\Acceso;

use App\Models\Compra;

/**
 * Veredicto de un escaneo en el acceso.
 *
 * Los motivos son códigos estables para que el torniquete pueda reaccionar
 * distinto según el caso (sonido, color, mensaje) sin depender del texto.
 */
final class ResultadoAcceso
{
    public const TOKEN_INVALIDO   = 'token_invalido';
    public const NO_ENCONTRADA    = 'no_encontrada';
    public const NO_PAGADA        = 'no_pagada';
    public const FECHA_DISTINTA   = 'fecha_distinta';
    public const QR_REEMPLAZADO   = 'qr_reemplazado';
    public const SIN_PASES        = 'sin_pases';
    public const CANCELADA        = 'cancelada';

    private function __construct(
        public readonly bool $permitido,
        public readonly ?string $motivo,
        public readonly string $mensaje,
        public readonly ?Compra $compra = null,
        public readonly int $pasesConsumidos = 0,
    ) {
    }

    /**
     * El pase es válido y tiene cupo, pero TODAVÍA NO se consumió nada.
     * Es lo que devuelve la consulta previa: el operador ve cuántos pases
     * quedan y decide cuántos entran.
     */
    public static function consultable(Compra $compra): self
    {
        $restantes = $compra->pasesDisponibles();

        return new self(
            permitido: true,
            motivo: null,
            mensaje: "Pase válido. {$restantes} " . ($restantes === 1 ? 'persona puede' : 'personas pueden') . ' entrar.',
            compra: $compra,
            pasesConsumidos: 0,
        );
    }

    public static function permitido(Compra $compra, int $pases): self
    {
        $restantes = $compra->pasesDisponibles();

        return new self(
            permitido: true,
            motivo: null,
            mensaje: $restantes > 0
                ? "Acceso autorizado para {$pases}. Quedan {$restantes} pases."
                : "Acceso autorizado para {$pases}. Último uso de este código.",
            compra: $compra,
            pasesConsumidos: $pases,
        );
    }

    public static function rechazado(string $motivo, string $mensaje, ?Compra $compra = null): self
    {
        return new self(
            permitido: false,
            motivo: $motivo,
            mensaje: $mensaje,
            compra: $compra,
        );
    }
}
