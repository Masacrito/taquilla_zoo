<?php

namespace App\Services\Reporte;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas de visitantes (brief §9, Fase 3).
 *
 * Se cuentan PASES, no compras: lo que le interesa al zoológico es cuánta
 * gente entró y de dónde vino, no cuántas transacciones hubo.
 *
 * La procedencia se lee de `compra_detalle`, no de `clientes`, porque se
 * captura POR RENGLÓN (§5.4): un mismo comprador puede traer gente de
 * distintos lugares, y contar todo al domicilio del que pagó distorsionaría
 * el dato.
 */
class EstadisticaService
{
    public function generar(string $desde, string $hasta): array
    {
        return [
            'desde'              => $desde,
            'hasta'              => $hasta,
            'resumen'            => $this->resumen($desde, $hasta),
            'por_nacionalidad'   => $this->porCatalogo($desde, $hasta, 'nacionalidades', 'id_nacionalidad'),
            'por_subnacionalidad'=> $this->porCatalogo($desde, $hasta, 'subnacionalidades', 'id_subnacionalidad'),
            'por_tipo_acceso'    => $this->porCatalogo($desde, $hasta, 'tipos_acceso', 'id_tipo_acceso'),
            'por_estado'         => $this->porEstado($desde, $hasta),
            'por_municipio'      => $this->porMunicipio($desde, $hasta),
            'por_pais'           => $this->porPais($desde, $hasta),
            'por_dia'            => $this->porDia($desde, $hasta),
        ];
    }

    /**
     * Solo cuenta gente que EFECTIVAMENTE entró: los pases consumidos en el
     * acceso, no los vendidos. Una compra pagada de la que nadie se presentó
     * no es una visita.
     */
    private function baseQuery(string $desde, string $hasta)
    {
        return DB::table('compra_detalle')
            ->join('compras', 'compra_detalle.id_compra', '=', 'compras.id')
            ->whereNull('compras.deleted_at')
            ->whereIn('compras.estado', ['acceso_parcial', 'utilizada'])
            ->whereDate('compras.fecha_visita', '>=', $desde)
            ->whereDate('compras.fecha_visita', '<=', $hasta);
    }

    private function resumen(string $desde, string $hasta): array
    {
        $fila = $this->baseQuery($desde, $hasta)
            ->selectRaw('COALESCE(SUM(compra_detalle.cantidad), 0) AS pases')
            ->selectRaw('COALESCE(SUM(compra_detalle.cant_hombre), 0) AS hombres')
            ->selectRaw('COALESCE(SUM(compra_detalle.cant_mujer), 0) AS mujeres')
            ->selectRaw('COUNT(DISTINCT compras.id) AS compras')
            ->selectRaw('COUNT(DISTINCT compras.id_cliente) AS clientes')
            ->first();

        return [
            'pases'    => (int) $fila->pases,
            'hombres'  => (int) $fila->hombres,
            'mujeres'  => (int) $fila->mujeres,
            'compras'  => (int) $fila->compras,
            'clientes' => (int) $fila->clientes,
        ];
    }

    private function porCatalogo(string $desde, string $hasta, string $tabla, string $llave): Collection
    {
        return $this->baseQuery($desde, $hasta)
            ->join($tabla, "compra_detalle.{$llave}", '=', "{$tabla}.id")
            ->groupBy("{$tabla}.nombre")
            ->orderByDesc(DB::raw('SUM(compra_detalle.cantidad)'))
            ->get([
                "{$tabla}.nombre AS etiqueta",
                DB::raw('SUM(compra_detalle.cantidad) AS pases'),
                DB::raw('SUM(compra_detalle.cant_hombre) AS hombres'),
                DB::raw('SUM(compra_detalle.cant_mujer) AS mujeres'),
            ]);
    }

    private function porEstado(string $desde, string $hasta): Collection
    {
        return $this->baseQuery($desde, $hasta)
            ->join('estados', 'compra_detalle.id_estado', '=', 'estados.id')
            ->groupBy('estados.nombre')
            ->orderByDesc(DB::raw('SUM(compra_detalle.cantidad)'))
            ->limit(32)
            ->get(['estados.nombre AS etiqueta', DB::raw('SUM(compra_detalle.cantidad) AS pases')]);
    }

    private function porMunicipio(string $desde, string $hasta): Collection
    {
        return $this->baseQuery($desde, $hasta)
            ->join('municipios', 'compra_detalle.id_municipio', '=', 'municipios.id')
            ->groupBy('municipios.nombre')
            ->orderByDesc(DB::raw('SUM(compra_detalle.cantidad)'))
            ->limit(20)
            ->get(['municipios.nombre AS etiqueta', DB::raw('SUM(compra_detalle.cantidad) AS pases')]);
    }

    private function porPais(string $desde, string $hasta): Collection
    {
        return $this->baseQuery($desde, $hasta)
            ->join('paises', 'compra_detalle.id_pais', '=', 'paises.id')
            ->groupBy('paises.nombre')
            ->orderByDesc(DB::raw('SUM(compra_detalle.cantidad)'))
            ->limit(20)
            ->get(['paises.nombre AS etiqueta', DB::raw('SUM(compra_detalle.cantidad) AS pases')]);
    }

    private function porDia(string $desde, string $hasta): Collection
    {
        return $this->baseQuery($desde, $hasta)
            ->groupBy('compras.fecha_visita')
            ->orderBy('compras.fecha_visita')
            ->get([
                'compras.fecha_visita AS dia',
                DB::raw('SUM(compra_detalle.cantidad) AS pases'),
            ]);
    }
}
