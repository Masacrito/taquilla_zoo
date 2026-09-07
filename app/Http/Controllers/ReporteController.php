<?php

namespace App\Http\Controllers;

use App\Services\Reporte\CorteIngresosService;
use App\Services\Reporte\EstadisticaService;
use Illuminate\Http\Request;

/**
 * Cortes de ingresos y estadísticas (brief §7, §9).
 *
 *   /admin/cortes        → permiso generar_cortes (Administrador y Taquilla)
 *   /admin/estadisticas  → permiso ver_estadisticas (solo Administrador)
 */
class ReporteController extends Controller
{
    public function cortes(Request $request, CorteIngresosService $servicio)
    {
        [$desde, $hasta] = $this->rango($request);

        $base = $request->query('base') === CorteIngresosService::POR_VISITA
            ? CorteIngresosService::POR_VISITA
            : CorteIngresosService::POR_COMPRA;

        return view('admin.reportes.cortes', [
            'corte' => $servicio->generar($desde, $hasta, $base),
        ]);
    }

    public function estadisticas(Request $request, EstadisticaService $servicio)
    {
        [$desde, $hasta] = $this->rango($request);

        return view('admin.reportes.estadisticas', [
            'datos' => $servicio->generar($desde, $hasta),
        ]);
    }

    /**
     * Por omisión, el día de hoy: es el uso más frecuente (el corte al
     * cerrar la taquilla).
     */
    private function rango(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        return [
            $request->query('desde', today()->toDateString()),
            $request->query('hasta', today()->toDateString()),
        ];
    }
}
