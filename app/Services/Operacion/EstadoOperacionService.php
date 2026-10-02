<?php

namespace App\Services\Operacion;

use App\Models\AforoDiario;
use Illuminate\Support\Carbon;

/**
 * ¿Está abierto el zoológico en este momento?
 *
 * Se responde con el calendario REAL, no con una regla fija de horarios. Por
 * eso puede decir «Hoy cerrado: fumigación» y no solo «lunes cerrado»: el
 * motivo sale de `aforo_diario`, que es donde el personal marca los cierres.
 *
 * Eso es lo que separa un portal conectado al sistema de un folleto con el
 * horario escrito a mano.
 */
class EstadoOperacionService
{
    /**
     * @return array{abierto: bool, titulo: string, detalle: ?string}
     */
    public function ahora(): array
    {
        $ahora = now();
        $dia   = AforoDiario::where('fecha', $ahora->toDateString())->first();

        // Ni siquiera está dado de alta: no hay visita posible.
        if ($dia === null) {
            return [
                'abierto' => false,
                'titulo'  => 'Cerrado hoy',
                'detalle' => $this->cuandoAbre(),
            ];
        }

        if ($dia->cerrado) {
            return [
                'abierto' => false,
                'titulo'  => 'Cerrado hoy',
                // El motivo real que capturó el personal. Si no hay, se cae al
                // caso más común, que es el lunes.
                'detalle' => $dia->motivo_cierre ?: $this->cuandoAbre(),
            ];
        }

        [$apertura, $cierre] = $this->horario($ahora);

        if ($ahora->lt($apertura)) {
            return [
                'abierto' => false,
                'titulo'  => 'Abre hoy',
                'detalle' => 'a las ' . $apertura->format('H:i') . ' hrs',
            ];
        }

        if ($ahora->gt($cierre)) {
            return [
                'abierto' => false,
                'titulo'  => 'Cerrado por hoy',
                'detalle' => $this->cuandoAbre(),
            ];
        }

        return [
            'abierto' => true,
            'titulo'  => 'Abierto ahora',
            'detalle' => 'cierra a las ' . $cierre->format('H:i') . ' hrs',
        ];
    }

    /**
     * El próximo día abierto, en palabras.
     *
     * Sale del calendario y no de «martes a domingo»: si el personal cerró el
     * martes por contingencia, esto lo sabe y dice el miércoles.
     */
    private function cuandoAbre(): ?string
    {
        $siguiente = AforoDiario::where('fecha', '>', now()->toDateString())
            ->where('cerrado', false)
            ->orderBy('fecha')
            ->first();

        if ($siguiente === null) {
            return null;
        }

        $fecha = $siguiente->fecha;
        $hora  = config('taquilla.horario.apertura');

        if ($fecha->isTomorrow()) {
            return "abre mañana a las {$hora} hrs";
        }

        return 'abre el ' . $fecha->translatedFormat('l d \d\e F') . " a las {$hora} hrs";
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function horario(Carbon $dia): array
    {
        return [
            $this->conHora($dia, config('taquilla.horario.apertura')),
            $this->conHora($dia, config('taquilla.horario.cierre')),
        ];
    }

    private function conHora(Carbon $dia, string $hhmm): Carbon
    {
        [$hora, $minuto] = array_map('intval', explode(':', $hhmm));

        return $dia->copy()->setTime($hora, $minuto);
    }
}
