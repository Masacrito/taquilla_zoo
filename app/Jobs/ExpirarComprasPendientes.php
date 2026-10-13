<?php

namespace App\Jobs;

use App\Models\Compra;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expira las compras que llevan 15 minutos sin pagarse (brief §5.7).
 *
 * Ya no libera aforo —no hay cupo que liberar—, pero el job sigue siendo
 * necesario: sin él, un carrito abandonado se queda como `pendiente_pago`
 * para siempre y ensucia cortes, conciliación y la vista del visitante.
 *
 * Programado en routes/console.php.
 */
class ExpirarComprasPendientes implements ShouldQueue
{
    use Queueable;

    public function handle(BitacoraService $bitacora): void
    {
        $vencidas = Compra::pendientesVencidas()->get();

        if ($vencidas->isEmpty()) {
            return;
        }

        $expiradas = 0;

        foreach ($vencidas as $compra) {
            DB::transaction(function () use ($compra, $bitacora, &$expiradas) {

                // Relee con bloqueo: si un webhook la está pagando justo
                // ahora, no debemos expirarla. Tampoco si el comprador la
                // retomó entretanto: eso le reinició el reloj (2026-10-11).
                $fresca = Compra::pendientesVencidas()
                    ->when(DB::connection()->getDriverName() !== 'sqlite',
                        fn ($q) => $q->lockForUpdate())
                    ->find($compra->id);

                if (! $fresca) {
                    return;
                }

                $fresca->estado = Compra::EXPIRADA;
                // Una expirada ya no sirve de nada al visitante: sale sola de
                // su bandeja para no saturarla.
                $fresca->archivada_en = now();
                $fresca->save();

                $bitacora->registrar('compras', BitacoraService::UPDATE, (string) $fresca->id, [
                    'folio'           => $fresca->folio,
                    'estado'       => Compra::EXPIRADA,
                    'fecha_visita' => $fresca->fecha_visita->toDateString(),
                ]);

                $expiradas++;
            });
        }

        if ($expiradas > 0) {
            Log::info("Compras expiradas por falta de pago: {$expiradas}");
        }
    }
}
