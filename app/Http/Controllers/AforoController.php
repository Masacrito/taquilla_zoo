<?php

namespace App\Http\Controllers;

use App\Models\AforoDiario;
use App\Models\Compra;
use App\Services\Auditoria\BitacoraService;
use App\Services\Operacion\GenerarCalendarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Calendario de operación (brief §5.6).
 *
 * No administra cupo: no hay aforo máximo ni mínimo. Lo único que se decide
 * aquí es qué días abre el zoológico. Los lunes se generan cerrados porque el
 * ZooMAT no abre; el resto de la semana opera de 8:30 a 16:00, y cualquier día
 * puede cerrarse por contingencia.
 *
 * El calendario se extiende solo (App\Jobs\GenerarCalendario); esta pantalla
 * queda para revisar lo generado, cerrar días y abrir rangos fuera de lo
 * habitual.
 */
class AforoController extends Controller
{
    public function __construct(
        private readonly BitacoraService $bitacora,
        private readonly GenerarCalendarioService $calendario,
    ) {
    }

    public function index(Request $request)
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? (clone $desde)->endOfMonth();

        // Tope defensivo: evita que alguien pida 10 años en una pantalla.
        if ($desde->diffInDays($hasta) > 366) {
            $hasta = (clone $desde)->addDays(366);
        }

        $rango = [$desde->toDateString(), $hasta->toDateString()];

        $dias = AforoDiario::whereBetween('fecha', $rango)->orderBy('fecha')->get();

        return view('admin.aforo.index', [
            'dias'       => $dias,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'vendido'    => $this->vendidoPorFecha($rango),
            'pendientes' => $dias->filter->pendienteDeRevision()->count(),
        ]);
    }

    /**
     * Crea los días que falten en el rango. No pisa los que ya existen: si a un
     * día se le cerró la operación a mano, se respeta.
     */
    public function generar(Request $request)
    {
        $datos = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ], [], [
            'desde' => 'fecha inicial',
            'hasta' => 'fecha final',
        ]);

        $desde = Carbon::parse($datos['desde']);
        $hasta = Carbon::parse($datos['hasta']);

        if ($desde->diffInDays($hasta) > 366) {
            return back()->with('error', 'El rango no puede exceder un año.');
        }

        $resultado = $this->calendario->generar($desde, $hasta);

        $this->bitacora->registrar('aforo_diario', BitacoraService::CREATE, null, [
            'desde'   => $desde->toDateString(),
            'hasta'   => $hasta->toDateString(),
            'creados' => $resultado->creados,
        ]);

        if ($resultado->creados === 0) {
            return back()->with('error', 'Todos los días de ese rango ya existían. No se modificó ninguno.');
        }

        return back()->with('success',
            "Se generaron {$resultado->creados} días ({$resultado->lunes} lunes quedaron cerrados). Los días que ya existían no se tocaron.");
    }

    /**
     * Da por vistos los días automáticos del rango. No cambia si abren o no:
     * solo apaga el aviso del tablero.
     */
    public function revisar(Request $request)
    {
        $datos = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $revisados = AforoDiario::pendientesDeRevision()
            ->whereBetween('fecha', [
                Carbon::parse($datos['desde'])->toDateString(),
                Carbon::parse($datos['hasta'])->toDateString(),
            ])
            ->update(['revisado_en' => now()]);

        if ($revisados === 0) {
            return back()->with('error', 'No había días pendientes de revisión en ese rango.');
        }

        $this->bitacora->registrar('aforo_diario', BitacoraService::UPDATE, null, [
            'desde'     => $datos['desde'],
            'hasta'     => $datos['hasta'],
            'revisados' => $revisados,
        ]);

        return back()->with('success', $revisados === 1
            ? 'Se marcó 1 día como revisado.'
            : "Se marcaron {$revisados} días como revisados.");
    }

    public function update(Request $request, string $fecha)
    {
        $dia = AforoDiario::findOrFail($fecha);

        $datos = $request->validate([
            'cerrado'       => ['nullable', 'boolean'],
            'motivo_cierre' => ['nullable', 'string', 'max:160'],
        ], [], [
            'motivo_cierre' => 'motivo de cierre',
        ]);

        $anterior = $dia->getOriginal();

        $dia->cerrado       = $request->boolean('cerrado');
        $dia->motivo_cierre = $dia->cerrado ? ($datos['motivo_cierre'] ?? null) : null;

        // Quien edita un día ya lo miró.
        if ($dia->pendienteDeRevision()) {
            $dia->revisado_en = now();
        }

        $dia->save();

        $this->bitacora->actualizado($dia, $anterior);

        return back()->with('success',
            $dia->cerrado ? "El {$fecha} quedó cerrado." : "El {$fecha} quedó abierto.");
    }

    /**
     * Compras con boleto vigente por fecha de visita, para que quien vaya a
     * cerrar un día sepa a cuánta gente afecta. Cerrar no cancela nada: esas
     * compras siguen siendo válidas y habría que atenderlas una por una.
     *
     * @return \Illuminate\Support\Collection<string, object{compras: int, pases: int}>
     */
    private function vendidoPorFecha(array $rango)
    {
        return Compra::query()
            // whereDate y no whereBetween: SQLite guarda la fecha con hora y
            // el último día del rango quedaría fuera (igual que EstadisticaService).
            ->whereDate('fecha_visita', '>=', $rango[0])
            ->whereDate('fecha_visita', '<=', $rango[1])
            ->whereIn('estado', [Compra::PAGADA, Compra::ACCESO_PARCIAL])
            ->selectRaw('fecha_visita, COUNT(*) AS compras, SUM(pases_total - pases_usados) AS pases')
            ->groupBy('fecha_visita')
            ->toBase()
            ->get()
            // SQLite devuelve la fecha con hora; PostgreSQL, sin ella.
            ->keyBy(fn ($fila) => substr((string) $fila->fecha_visita, 0, 10));
    }
}
