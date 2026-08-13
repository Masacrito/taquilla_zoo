<?php

namespace App\Services\Venta;

use App\Models\AforoDiario;
use App\Models\Compra;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancelación administrativa de una compra (brief §5.7, §7).
 *
 * Cancelar NO borra: cambia el estado y conserva el folio (§4.6). Lo que sí
 * hace es devolver los pases al cupo del día, para que ese lugar vuelva a
 * estar a la venta.
 *
 * Solo se liberan los pases NO usados: si alguien ya entró con dos de cuatro
 * pases, el zoológico no puede revender esos dos.
 */
class CancelarCompraService
{
    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function cancelar(Compra $compra, string $motivo): Compra
    {
        return DB::transaction(function () use ($compra, $motivo) {

            $fresca = Compra::query()
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn ($q) => $q->lockForUpdate())
                ->findOrFail($compra->id);

            if (! $fresca->puedeTransicionarA(Compra::CANCELADA)) {
                throw new RuntimeException(
                    "No se puede cancelar una compra en estado «{$fresca->estado}»."
                );
            }

            $pasesALiberar = $fresca->pasesDisponibles();

            $fresca->estado = Compra::CANCELADA;
            $fresca->observaciones = trim(($fresca->observaciones ? $fresca->observaciones . "\n" : '')
                . 'Cancelada: ' . $motivo);
            $fresca->save();

            if ($pasesALiberar > 0) {
                AforoDiario::liberar($fresca->fecha_visita->toDateString(), $pasesALiberar);
            }

            $this->bitacora->registrar('compras', BitacoraService::UPDATE, (string) $fresca->id, [
                'folio'           => $fresca->folio,
                'estado'          => Compra::CANCELADA,
                'motivo'          => $motivo,
                'pases_liberados' => $pasesALiberar,
                'pases_usados'    => $fresca->pases_usados,
            ]);

            return $fresca;
        });
    }
}
