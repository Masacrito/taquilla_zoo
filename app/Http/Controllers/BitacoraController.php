<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;

/**
 * Consulta de la bitácora de auditoría (brief §4.7).
 *
 * Solo lectura: `movimientos` es una tabla de inserción exclusiva. No hay
 * edición ni borrado, a propósito.
 */
class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $movimientos = Movimiento::with('cuenta.usuario')
            ->when($request->filled('tabla'), fn ($q) => $q->where('tabla', $request->string('tabla')))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->string('accion')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->orderByDesc('fecha')
            ->orderByDesc('id_movimiento')
            ->paginate(50)
            ->withQueryString();

        return view('admin.bitacora.index', [
            'movimientos' => $movimientos,
            'tablas'      => Movimiento::query()->distinct()->orderBy('tabla')->pluck('tabla'),
            'acciones'    => Movimiento::query()->distinct()->orderBy('accion')->pluck('accion'),
        ]);
    }
}
