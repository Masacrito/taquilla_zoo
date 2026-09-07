<?php

namespace App\Services\Venta;

use App\Models\Compra;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancelación administrativa de una compra (brief §5.7, §7).
 *
 * Cancelar NO borra: cambia el estado, conserva el folio y anota el motivo
 * (§4.6). No devuelve lugares a ningún cupo porque no hay cupo que devolver.
 *
 * Los pases ya usados quedan como están: si alguien entró con dos de cuatro,
 * ese registro de acceso no se deshace.
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

            $fresca->estado = Compra::CANCELADA;
            $fresca->observaciones = trim(($fresca->observaciones ? $fresca->observaciones . "\n" : '')
                . 'Cancelada: ' . $motivo);
            $fresca->save();

            $this->bitacora->registrar('compras', BitacoraService::UPDATE, (string) $fresca->id, [
                'folio'           => $fresca->folio,
                'estado'          => Compra::CANCELADA,
                'motivo'          => $motivo,
                'pases_usados'    => $fresca->pases_usados,
            ]);

            return $fresca;
        });
    }
}
