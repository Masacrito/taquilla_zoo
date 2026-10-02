<?php

namespace App\Services\Operacion;

use App\Models\AforoDiario;
use Illuminate\Support\Carbon;

/**
 * Abre días en el calendario de operación.
 *
 * Es la única implementación de «generar días»: la usan el formulario del
 * panel, la tarea diaria que mantiene el calendario adelantado y el seeder.
 * Antes la lógica vivía dentro del controlador y el seeder tenía su copia.
 *
 * Nunca pisa un día que ya existe: si alguien cerró una fecha a mano, volver
 * a generar el rango no la reabre.
 */
class GenerarCalendarioService
{
    public const MOTIVO_LUNES = 'Lunes: el zoológico no abre.';

    /**
     * Crea los días que falten entre las dos fechas, ambas incluidas.
     *
     * El origen es lo que decide si el día queda pendiente de revisión: solo
     * los automáticos lo están (AforoDiario::scopePendientesDeRevision).
     */
    public function generar(Carbon $desde, Carbon $hasta, string $origen = AforoDiario::MANUAL): ResultadoGeneracion
    {
        $existentes = AforoDiario::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->map(fn (AforoDiario $dia) => $dia->fecha->toDateString())
            ->flip();

        $creados = 0;
        $lunes   = 0;

        for ($dia = $desde->copy()->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $fecha = $dia->toDateString();

            if ($existentes->has($fecha)) {
                continue;
            }

            $esLunes = AforoDiario::esLunes($dia);

            AforoDiario::create([
                'fecha'         => $fecha,
                'cerrado'       => $esLunes,
                'motivo_cierre' => $esLunes ? self::MOTIVO_LUNES : null,
                'origen'        => $origen,
            ]);

            $creados++;
            $esLunes && $lunes++;
        }

        return new ResultadoGeneracion($creados, $lunes);
    }

    /**
     * Mantiene abiertos todos los meses que el portal ofrece a la venta.
     *
     * El calendario público pinta `meses_a_la_venta` meses contando el actual;
     * si el último no tuviera días, el visitante vería un mes entero en gris.
     * Se llama a diario y solo crea algo cuando empieza un mes nuevo (o cuando
     * el calendario se había quedado corto).
     */
    public function asegurarHorizonte(): ResultadoGeneracion
    {
        $meses = max(1, (int) config('taquilla.calendario.meses_a_la_venta'));

        return $this->generar(
            Carbon::today(),
            Carbon::today()->startOfMonth()->addMonths($meses - 1)->endOfMonth(),
            AforoDiario::AUTOMATICO,
        );
    }
}
