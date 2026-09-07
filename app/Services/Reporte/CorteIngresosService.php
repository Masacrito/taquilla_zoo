<?php

namespace App\Services\Reporte;

use App\Models\Compra;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Corte de ingresos (brief §9, Fase 3).
 *
 * ═══════════════════════════════════════════════════════════════════════
 *  EL CORTE SE ARMA CON LOS PRECIOS CONGELADOS.
 *
 *  Todos los importes salen de `compra_detalle.importe_centavos` y
 *  `precio_centavos_snap`, NUNCA de `rubros`. Ese es el motivo de existir
 *  del snapshot (§4.3): si el zoológico sube una tarifa hoy, el corte del
 *  mes pasado tiene que seguir dando exactamente lo mismo.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * Todo se suma en centavos enteros (§4.1). No hay un solo float aquí: los
 * cortes tienen que cuadrar al centavo.
 */
class CorteIngresosService
{
    /** Se cobró el dinero: son los estados que cuentan para el corte. */
    public const ESTADOS_COBRADOS = [
        Compra::PAGADA,
        Compra::ACCESO_PARCIAL,
        Compra::UTILIZADA,
        Compra::VENCIDA,       // no vino, pero pagó
    ];

    /** Base de agrupación: cuándo entró el dinero, o cuándo es la visita. */
    public const POR_COMPRA = 'compra';
    public const POR_VISITA = 'visita';

    public function generar(string $desde, string $hasta, string $base = self::POR_COMPRA): array
    {
        $columna = $base === self::POR_VISITA ? 'compras.fecha_visita' : 'compras.fecha_compra';

        return [
            'desde'        => $desde,
            'hasta'        => $hasta,
            'base'         => $base,
            'resumen'      => $this->resumen($columna, $desde, $hasta),
            'por_rubro'    => $this->porRubro($columna, $desde, $hasta),
            'por_dia'      => $this->porDia($columna, $desde, $hasta),
            'no_cobradas'  => $this->noCobradas($columna, $desde, $hasta),
        ];
    }

    private function baseQuery(string $columna, string $desde, string $hasta)
    {
        return DB::table('compra_detalle')
            ->join('compras', 'compra_detalle.id_compra', '=', 'compras.id')
            ->whereNull('compras.deleted_at')
            ->whereIn('compras.estado', self::ESTADOS_COBRADOS)
            ->whereDate($columna, '>=', $desde)
            ->whereDate($columna, '<=', $hasta);
    }

    private function resumen(string $columna, string $desde, string $hasta): array
    {
        $fila = $this->baseQuery($columna, $desde, $hasta)
            ->selectRaw('COALESCE(SUM(compra_detalle.importe_centavos), 0) AS total')
            ->selectRaw('COALESCE(SUM(compra_detalle.cantidad), 0) AS pases')
            ->selectRaw('COALESCE(SUM(compra_detalle.cant_hombre), 0) AS hombres')
            ->selectRaw('COALESCE(SUM(compra_detalle.cant_mujer), 0) AS mujeres')
            ->selectRaw('COUNT(DISTINCT compras.id) AS compras')
            ->first();

        return [
            'total_centavos' => (int) $fila->total,
            'pases'          => (int) $fila->pases,
            'hombres'        => (int) $fila->hombres,
            'mujeres'        => (int) $fila->mujeres,
            'compras'        => (int) $fila->compras,
        ];
    }

    /**
     * Se agrupa por el NOMBRE CONGELADO, no por id_rubro: si un rubro se
     * renombró o se dio de baja, el corte histórico debe seguir mostrando
     * el concepto tal como se vendió.
     */
    private function porRubro(string $columna, string $desde, string $hasta): Collection
    {
        return $this->baseQuery($columna, $desde, $hasta)
            ->groupBy('compra_detalle.rubro_nombre_snap', 'compra_detalle.precio_centavos_snap')
            ->orderByDesc(DB::raw('SUM(compra_detalle.importe_centavos)'))
            ->get([
                'compra_detalle.rubro_nombre_snap AS concepto',
                'compra_detalle.precio_centavos_snap AS precio_centavos',
                DB::raw('SUM(compra_detalle.cantidad) AS pases'),
                DB::raw('SUM(compra_detalle.cant_hombre) AS hombres'),
                DB::raw('SUM(compra_detalle.cant_mujer) AS mujeres'),
                DB::raw('SUM(compra_detalle.importe_centavos) AS total_centavos'),
            ]);
    }

    private function porDia(string $columna, string $desde, string $hasta): Collection
    {
        $dia = DB::raw("CAST({$columna} AS DATE)");

        return $this->baseQuery($columna, $desde, $hasta)
            ->groupBy($dia)
            ->orderBy($dia)
            ->get([
                DB::raw("CAST({$columna} AS DATE) AS dia"),
                DB::raw('SUM(compra_detalle.cantidad) AS pases'),
                DB::raw('SUM(compra_detalle.importe_centavos) AS total_centavos'),
                DB::raw('COUNT(DISTINCT compras.id) AS compras'),
            ]);
    }

    /**
     * Lo que NO entró: sirve para explicar la diferencia entre lo vendido y
     * lo cobrado sin tener que salir a buscarlo.
     */
    private function noCobradas(string $columna, string $desde, string $hasta): Collection
    {
        return DB::table('compras')
            ->whereNull('deleted_at')
            ->whereNotIn('estado', self::ESTADOS_COBRADOS)
            ->whereDate(str_replace('compras.', '', $columna), '>=', $desde)
            ->whereDate(str_replace('compras.', '', $columna), '<=', $hasta)
            ->groupBy('estado')
            ->orderBy('estado')
            ->get([
                'estado',
                DB::raw('COUNT(*) AS compras'),
                DB::raw('SUM(total_centavos) AS total_centavos'),
            ]);
    }
}
