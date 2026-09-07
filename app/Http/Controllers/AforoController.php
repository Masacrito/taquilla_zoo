<?php

namespace App\Http\Controllers;

use App\Models\AforoDiario;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Calendario de operación (brief §5.6).
 *
 * No administra cupo: no hay aforo máximo ni mínimo. Lo único que se decide
 * aquí es qué días abre el zoológico. Los lunes se generan cerrados porque el
 * ZooMAT no abre; el resto de la semana opera de 8:30 a 16:00, y cualquier día
 * puede cerrarse por contingencia.
 */
class AforoController extends Controller
{
    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function index(Request $request)
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? (clone $desde)->endOfMonth();

        // Tope defensivo: evita que alguien pida 10 años en una pantalla.
        if ($desde->diffInDays($hasta) > 366) {
            $hasta = (clone $desde)->addDays(366);
        }

        $dias = AforoDiario::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')
            ->get();

        return view('admin.aforo.index', [
            'dias'  => $dias,
            'desde' => $desde,
            'hasta' => $hasta,
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

        $existentes = AforoDiario::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->pluck('fecha')
            ->map(fn ($f) => Carbon::parse($f)->toDateString())
            ->flip();

        $creados = 0;
        $lunes   = 0;

        for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
            $fecha = $dia->toDateString();

            if ($existentes->has($fecha)) {
                continue;
            }

            $esLunes = AforoDiario::esLunes($dia);

            AforoDiario::create([
                'fecha'         => $fecha,
                'cerrado'       => $esLunes,
                'motivo_cierre' => $esLunes ? 'Lunes: el zoológico no abre.' : null,
            ]);

            $creados++;
            $esLunes && $lunes++;
        }

        $this->bitacora->registrar('aforo_diario', BitacoraService::CREATE, null, [
            'desde'   => $desde->toDateString(),
            'hasta'   => $hasta->toDateString(),
            'creados' => $creados,
        ]);

        if ($creados === 0) {
            return back()->with('error', 'Todos los días de ese rango ya existían. No se modificó ninguno.');
        }

        return back()->with('success',
            "Se generaron {$creados} días ({$lunes} lunes quedaron cerrados). Los días que ya existían no se tocaron.");
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
        $dia->save();

        $this->bitacora->actualizado($dia, $anterior);

        return back()->with('success',
            $dia->cerrado ? "El {$fecha} quedó cerrado." : "El {$fecha} quedó abierto.");
    }
}
