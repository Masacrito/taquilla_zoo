<?php

namespace App\Http\Controllers;

use App\Models\AforoDiario;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Cupo por día (brief §5.6).
 *
 * Los lunes se generan cerrados: el ZooMAT no abre. Horario del resto de la
 * semana: 8:30 a 16:00.
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
            'dias'            => $dias,
            'desde'           => $desde,
            'hasta'           => $hasta,
            'cupoPorOmision'  => AforoDiario::cupoPorOmision(),
        ]);
    }

    /**
     * Crea los días que falten en el rango. No pisa los que ya existen: si un
     * día ya tiene reservas o cupo ajustado a mano, se respeta.
     */
    public function generar(Request $request)
    {
        $datos = $request->validate([
            'desde'       => ['required', 'date'],
            'hasta'       => ['required', 'date', 'after_or_equal:desde'],
            'cupo_maximo' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [], [
            'desde' => 'fecha inicial',
            'hasta' => 'fecha final',
            'cupo_maximo' => 'cupo máximo',
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
                'cupo_maximo'   => $datos['cupo_maximo'],
                'reservados'    => 0,
                'cerrado'       => $esLunes,
                'motivo_cierre' => $esLunes ? 'Lunes: el zoológico no abre.' : null,
            ]);

            $creados++;
            $esLunes && $lunes++;
        }

        $this->bitacora->registrar('aforo_diario', BitacoraService::CREATE, null, [
            'desde'       => $desde->toDateString(),
            'hasta'       => $hasta->toDateString(),
            'cupo_maximo' => $datos['cupo_maximo'],
            'creados'     => $creados,
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
            'cupo_maximo'   => ['required', 'integer', 'min:0', 'max:100000'],
            'cerrado'       => ['nullable', 'boolean'],
            'motivo_cierre' => ['nullable', 'string', 'max:160'],
        ], [], [
            'cupo_maximo'   => 'cupo máximo',
            'motivo_cierre' => 'motivo de cierre',
        ]);

        // No se puede bajar el cupo por debajo de lo ya reservado: dejaría
        // compras pagadas sin lugar.
        if ($datos['cupo_maximo'] < $dia->reservados) {
            return back()->with('error',
                "No puedes fijar el cupo en {$datos['cupo_maximo']}: ese día ya tiene {$dia->reservados} pases reservados.");
        }

        $anterior = $dia->getOriginal();

        $dia->cupo_maximo   = $datos['cupo_maximo'];
        $dia->cerrado       = $request->boolean('cerrado');
        $dia->motivo_cierre = $dia->cerrado ? ($datos['motivo_cierre'] ?? null) : null;
        $dia->save();

        $this->bitacora->actualizado($dia, $anterior);

        return back()->with('success', "Aforo del {$fecha} actualizado.");
    }
}
