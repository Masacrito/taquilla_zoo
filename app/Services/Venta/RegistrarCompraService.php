<?php

namespace App\Services\Venta;

use App\Exceptions\DiaNoDisponibleException;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra una compra completa (brief §5.4, §4.5, §4.6).
 *
 * Todo ocurre dentro de UNA transacción:
 *   1. Folio consecutivo, tomando el contador con bloqueo de fila.
 *   2. Cabecera de la compra en estado `pendiente_pago`.
 *   3. Detalle con el precio y el nombre del rubro CONGELADOS.
 *
 * Si algo falla, se revierte todo y no quedan folios huérfanos.
 *
 * No se reserva cupo: no hay aforo máximo. Lo único que se valida de la fecha
 * es que el día exista en el calendario y esté abierto.
 */
class RegistrarCompraService
{
    private const SERIE_FOLIO = 'ZM';

    public function registrar(
        Cliente $cliente,
        Cotizacion $cotizacion,
        string $fechaVisita,
        ?string $observaciones = null,
    ): Compra {
        return DB::transaction(function () use ($cliente, $cotizacion, $fechaVisita, $observaciones) {

            // Lo único que se valida de la fecha es que el día exista en el
            // calendario y no esté cerrado. Sin cupo que revisar no hay nada
            // que reservar, ni carrera que se pueda perder.
            $dia = AforoDiario::where('fecha', $fechaVisita)->first();

            if (! $dia || $dia->cerrado) {
                throw DiaNoDisponibleException::paraFecha($fechaVisita);
            }

            $compra = Compra::create([
                'folio'          => $this->siguienteFolio(),
                'id_cliente'     => $cliente->id,
                'fecha_compra'   => now(),
                'fecha_visita'   => $fechaVisita,
                'total_centavos' => $cotizacion->totalCentavos,
                'pases_total'    => $cotizacion->pasesTotal,
                'pases_usados'   => 0,
                'estado'         => Compra::PENDIENTE_PAGO,
                'observaciones'  => $observaciones,
            ]);

            foreach ($cotizacion->renglones as $renglon) {
                CompraDetalle::create([
                    'id_compra'            => $compra->id,
                    'id_rubro'             => $renglon->rubro->id,
                    // Congelados: cambiar la tarifa mañana no toca esta compra.
                    'rubro_nombre_snap'    => $renglon->nombre,
                    'precio_centavos_snap' => $renglon->precioCentavos,
                    'cant_hombre'          => $renglon->cantHombre,
                    'cant_mujer'           => $renglon->cantMujer,
                    'cantidad'             => $renglon->cantidad,
                    'importe_centavos'     => $renglon->importeCentavos,
                    'id_pais'              => $renglon->idPais,
                    'id_estado'            => $renglon->idEstado,
                    'id_municipio'         => $renglon->idMunicipio,
                    'id_nacionalidad'      => $renglon->rubro->id_nacionalidad,
                    'id_subnacionalidad'   => $renglon->rubro->id_subnacionalidad,
                    'id_tipo_acceso'       => $renglon->rubro->id_tipo_acceso,
                ]);
            }

            return $compra->load('detalle');
        });
    }

    /**
     * Folio consecutivo sin huecos (brief §4.6).
     *
     * Se bloquea la fila del contador con SELECT ... FOR UPDATE: las compras
     * concurrentes se serializan aquí. Si la transacción se revierte, el
     * número no se consume — que es exactamente lo que evita los huecos.
     *
     * Una secuencia de PostgreSQL NO serviría: no se revierte en rollback.
     */
    private function siguienteFolio(): string
    {
        $serie = self::SERIE_FOLIO;

        // lockForUpdate no existe en SQLite; ahí la transacción ya serializa.
        $consulta = DB::table('folios')->where('serie', $serie);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $consulta->lockForUpdate();
        }

        $fila = $consulta->first();

        if (! $fila) {
            DB::table('folios')->insert([
                'serie'      => $serie,
                'ultimo'     => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ultimo = 0;
        } else {
            $ultimo = (int) $fila->ultimo;
        }

        $siguiente = $ultimo + 1;

        DB::table('folios')->where('serie', $serie)->update([
            'ultimo'     => $siguiente,
            'updated_at' => now(),
        ]);

        return sprintf('%s-%s-%06d', $serie, Carbon::now()->format('Y'), $siguiente);
    }
}
