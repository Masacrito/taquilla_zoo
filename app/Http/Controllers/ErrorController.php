<?php

namespace App\Http\Controllers;

use App\Models\ErrorSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Consulta de los fallos del sistema (tabla `errores`).
 *
 * Separada de la bitácora de auditoría: `movimientos` responde quién cambió
 * qué, esto responde qué se rompió. Distinto permiso, distinta pantalla.
 *
 * Los fallos no se editan ni se borran desde aquí; lo único que se puede
 * hacer es marcarlos como atendidos, que deja constancia de quién los revisó.
 */
class ErrorController extends Controller
{
    public function index(Request $request)
    {
        $errores = ErrorSistema::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $busqueda = '%' . $request->string('q') . '%';
                $q->where(fn ($w) => $w->where('clase', 'like', $busqueda)
                    ->orWhere('mensaje', 'like', $busqueda)
                    ->orWhere('url', 'like', $busqueda));
            })
            // Por omisión solo lo pendiente: lo atendido ya lo vio alguien.
            ->when(! $request->boolean('todos'), fn ($q) => $q->pendientes())
            ->orderByDesc('ultima_vez')
            ->paginate(25)
            ->withQueryString();

        return view('admin.errores.index', [
            'errores'    => $errores,
            'pendientes' => ErrorSistema::pendientes()->count(),
            'mostrandoTodos' => $request->boolean('todos'),
        ]);
    }

    public function ver(ErrorSistema $error)
    {
        return view('admin.errores.ver', [
            'error' => $error->load(['cuenta.usuario', 'atendidoPor.usuario']),
        ]);
    }

    /**
     * Marca el fallo como revisado. No lo borra: de eso se encarga la purga
     * programada, pasados los días de retención.
     */
    public function atender(ErrorSistema $error)
    {
        if ($error->estaAtendido()) {
            return back()->with('error', 'Ese fallo ya estaba marcado como atendido.');
        }

        $error->update([
            'atendido_en'  => now(),
            'atendido_por' => Auth::id(),
        ]);

        return back()->with('success', "Fallo {$error->claseCorta()} marcado como atendido.");
    }

    public function reabrir(ErrorSistema $error)
    {
        $error->update(['atendido_en' => null, 'atendido_por' => null]);

        return back()->with('success', 'El fallo volvió a la lista de pendientes.');
    }
}
