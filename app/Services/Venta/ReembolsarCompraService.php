<?php

namespace App\Services\Venta;

use App\Models\Compra;
use App\Models\Pago;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Deja constancia de que una compra cancelada ya se reembolsó (brief §5.7).
 *
 * ponytail: esto NO mueve dinero. El convenio con el banco y la política de
 * reembolso siguen sin definirse (brief §12), así que el dinero se devuelve
 * por fuera —en el portal del banco— y aquí solo se registra que se hizo,
 * quién y por qué. Upgrade path: cuando haya pasarela real, llamar aquí a su
 * operación de reembolso antes de cambiar el estado, dentro de la misma
 * transacción.
 *
 * Solo desde `cancelada`: primero se invalida el QR, después se devuelve.
 */
class ReembolsarCompraService
{
    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function reembolsar(Compra $compra, string $motivo): Compra
    {
        return DB::transaction(function () use ($compra, $motivo) {

            $fresca = Compra::query()
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn ($q) => $q->lockForUpdate())
                ->findOrFail($compra->id);

            if (! $fresca->puedeTransicionarA(Compra::REEMBOLSADA)) {
                throw new RuntimeException(
                    "Solo se reembolsa una compra cancelada; esta está en estado «{$fresca->estado}»."
                );
            }

            $fresca->estado = Compra::REEMBOLSADA;
            $fresca->observaciones = trim(($fresca->observaciones ? $fresca->observaciones . "\n" : '')
                . 'Reembolsada: ' . $motivo);
            $fresca->save();

            // El cobro deja de contar como aprobado: así la conciliación no lo
            // suma como ingreso.
            $fresca->pagos()->where('estado', Pago::APROBADO)->update(['estado' => Pago::REEMBOLSADO]);

            $this->bitacora->registrar('compras', BitacoraService::UPDATE, (string) $fresca->id, [
                'folio'          => $fresca->folio,
                'estado'         => Compra::REEMBOLSADA,
                'motivo'         => $motivo,
                'total_centavos' => $fresca->total_centavos,
            ]);

            return $fresca;
        });
    }
}
