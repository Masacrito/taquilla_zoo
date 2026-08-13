<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

/**
 * Consulta de visitantes registrados (brief §7, permiso `gestion_clientes`).
 *
 * Solo lectura. Los datos del visitante los edita él desde su cuenta; desde
 * aquí no se modifican, para no dejar dudas sobre quién cambió qué.
 */
class ClienteAdminController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));

        $clientes = Cliente::query()
            ->withCount('compras')
            ->when($busqueda !== '', function ($q) use ($busqueda) {
                $termino = '%' . mb_strtolower($busqueda) . '%';
                $q->where(function ($sub) use ($termino) {
                    $sub->whereRaw('LOWER(correo) LIKE ?', [$termino])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', [$termino])
                        ->orWhereRaw('LOWER(apellidos) LIKE ?', [$termino]);
                });
            })
            ->when($request->query('estado') === 'verificados',
                fn ($q) => $q->whereNotNull('correo_verificado_en'))
            ->when($request->query('estado') === 'sin_verificar',
                fn ($q) => $q->whereNull('correo_verificado_en'))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.clientes.index', [
            'clientes' => $clientes,
            'busqueda' => $busqueda,
        ]);
    }

    public function ver(Cliente $cliente)
    {
        $cliente->load(['compras' => fn ($q) => $q->orderByDesc('fecha_compra')]);

        return view('admin.clientes.ver', ['cliente' => $cliente]);
    }
}
