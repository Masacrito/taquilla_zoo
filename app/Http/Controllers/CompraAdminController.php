<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Services\Venta\CancelarCompraService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Consulta y cancelación de compras desde el panel (brief §7).
 */
class CompraAdminController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));

        $compras = Compra::query()
            ->with('cliente')
            ->when($busqueda !== '', function ($q) use ($busqueda) {
                $termino = '%' . mb_strtoupper($busqueda) . '%';
                $q->whereRaw('UPPER(folio) LIKE ?', [$termino])
                    ->orWhereHas('cliente', fn ($c) => $c->whereRaw('LOWER(correo) LIKE ?', [mb_strtolower($busqueda) . '%']));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_visita', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_visita', '<=', $request->date('hasta')))
            ->orderByDesc('fecha_compra')
            ->paginate(30)
            ->withQueryString();

        return view('admin.compras.index', [
            'compras'  => $compras,
            'busqueda' => $busqueda,
            'estados'  => [
                Compra::PENDIENTE_PAGO, Compra::PAGADA, Compra::ACCESO_PARCIAL,
                Compra::UTILIZADA, Compra::EXPIRADA, Compra::VENCIDA,
                Compra::CANCELADA, Compra::REEMBOLSADA,
            ],
        ]);
    }

    public function ver(Compra $compra)
    {
        $compra->load(['cliente', 'detalle', 'pagos']);

        return view('admin.compras.ver', ['compra' => $compra]);
    }

    public function cancelar(Request $request, Compra $compra, CancelarCompraService $cancelador)
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:300'],
        ], [], ['motivo' => 'motivo de la cancelación']);

        try {
            $cancelador->cancelar($compra, $datos['motivo']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Compra {$compra->folio} cancelada. Los lugares no usados se liberaron.");
    }
}
