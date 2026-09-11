<?php

namespace App\Jobs;

use App\Models\ErrorSistema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Borra los fallos ya atendidos que pasaron los días de retención.
 *
 * Solo los ATENDIDOS: un fallo pendiente no se borra por viejo que sea,
 * porque entonces desaparecería sin que nadie lo haya visto. Si alguien lo
 * revisó y lo marcó, ya cumplió su función.
 *
 * A diferencia de `movimientos`, que se conserva indefinidamente por
 * auditoría, esta tabla se purga: son datos de diagnóstico, no de rendición
 * de cuentas.
 *
 * Programado en routes/console.php.
 */
class PurgarErroresAtendidos implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $dias = (int) config('taquilla.errores.dias_retencion', 90);

        if ($dias <= 0) {
            return;   // retención desactivada: no se purga nada
        }

        $borrados = ErrorSistema::whereNotNull('atendido_en')
            ->where('atendido_en', '<', now()->subDays($dias))
            ->delete();

        if ($borrados > 0) {
            Log::info("Purga de fallos: se borraron {$borrados} ya atendidos con más de {$dias} días.");
        }
    }
}
