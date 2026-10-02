<?php

namespace App\Jobs;

use App\Models\AforoDiario;
use App\Services\Auditoria\BitacoraService;
use App\Services\Operacion\GenerarCalendarioService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Mantiene el calendario adelantado sin que nadie tenga que acordarse.
 *
 * Antes, si el administrador no generaba días a tiempo, /comprar dejaba de
 * vender sin ningún síntoma. Corre a diario y es idempotente: en la práctica
 * solo crea algo el día 1 de cada mes, pero si el scheduler estuvo caído ese
 * día, la corrida siguiente lo repone.
 *
 * Los días nacen con origen `automatico` y sin revisar; el tablero avisa
 * hasta que un administrador los mire (y cierre los que no abran).
 *
 * Programado en routes/console.php.
 */
class GenerarCalendario implements ShouldQueue
{
    use Queueable;

    public function handle(GenerarCalendarioService $calendario, BitacoraService $bitacora): void
    {
        $resultado = $calendario->asegurarHorizonte();

        if ($resultado->creados === 0) {
            return;
        }

        $bitacora->registrar('aforo_diario', BitacoraService::CREATE, null, [
            'origen'  => AforoDiario::AUTOMATICO,
            'creados' => $resultado->creados,
            'lunes'   => $resultado->lunes,
        ]);

        Log::info("Calendario: se generaron {$resultado->creados} días automáticamente.");
    }
}
