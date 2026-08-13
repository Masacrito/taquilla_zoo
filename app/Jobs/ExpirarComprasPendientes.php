<?php

namespace App\Jobs;

use App\Models\AforoDiario;
use App\Models\Compra;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expira compras sin pagar y LIBERA el aforo que tenían reservado
 * (brief §5.7: a los 15 minutos).
 *
 * Sin esto, un carrito abandonado bloquea lugares para siempre y el
 * zoológico se queda vendiendo menos de su cupo real.
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
                // ahora, no debemos expirarla.
                $fresca = Compra::query()
                    ->when(DB::connection()->getDriverName() !== 'sqlite',
                        fn ($q) => $q->lockForUpdate())
                    ->find($compra->id);

                if (! $fresca || ! $fresca->puedeTransicionarA(Compra::EXPIRADA)) {
                    return;
                }

                $fresca->estado = Compra::EXPIRADA;
                $fresca->save();

                // Devolver los lugares al cupo del día.
                AforoDiario::liberar($fresca->fecha_visita->toDateString(), $fresca->pases_total);

                $bitacora->registrar('compras', BitacoraService::UPDATE, (string) $fresca->id, [
                    'folio'           => $fresca->folio,
                    'estado'          => Compra::EXPIRADA,
                    'pases_liberados' => $fresca->pases_total,
                    'fecha_visita'    => $fresca->fecha_visita->toDateString(),
                ]);

                $expiradas++;
            });
        }

        if ($expiradas > 0) {
            Log::info("Compras expiradas por falta de pago: {$expiradas}");
        }
    }
}
