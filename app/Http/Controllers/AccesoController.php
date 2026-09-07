<?php

namespace App\Http\Controllers;

use App\Models\Acceso;
use App\Models\Compra;
use App\Services\Acceso\ValidarAccesoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Módulo de accesos (brief §7).
 *
 *   /accesos/escanear  → permiso validar_accesos
 *   /accesos/bitacora  → permiso ver_bitacora_accesos
 */
class AccesoController extends Controller
{
    public function escanear()
    {
        return view('accesos.escanear', [
            'compradasHoy' => Compra::whereDate('fecha_visita', today())
                ->whereIn('estado', [Compra::PAGADA, Compra::ACCESO_PARCIAL])
                ->count(),
            'entradasHoy' => Acceso::whereDate('escaneado_en', today())
                ->where('resultado', Acceso::PERMITIDO)
                ->sum('pases_consumidos'),
        ]);
    }

    /**
     * Paso 1: revisa el pase SIN consumirlo.
     *
     * La cámara llama aquí. Así el operador ve cuántos pases quedan antes de
     * descontar nada, y enfocar un código dos veces no consume pases ni
     * ensucia la bitácora.
     */
    public function consultar(Request $request, ValidarAccesoService $validador)
    {
        $datos = $this->validarPeticion($request);

        return $this->respuesta($validador->consultar(
            codigo: $datos['codigo'],
            idTorniquete: $datos['id_torniquete'] ?? 'web',
            operador: Auth::guard('web')->user(),
        ));
    }

    /**
     * Paso 2: confirma la entrada y descuenta los pases.
     */
    public function validar(Request $request, ValidarAccesoService $validador)
    {
        $datos = $this->validarPeticion($request, conPases: true);

        return $this->respuesta($validador->validar(
            codigo: $datos['codigo'],
            pases: $datos['pases'] ?? 1,
            idTorniquete: $datos['id_torniquete'] ?? 'web',
            operador: Auth::guard('web')->user(),
        ));
    }

    private function validarPeticion(Request $request, bool $conPases = false): array
    {
        return $request->validate([
            // Puede ser el token del QR o el folio capturado a mano.
            'codigo'        => ['required', 'string', 'max:255'],
            'pases'         => [$conPases ? 'required' : 'nullable', 'integer', 'min:1', 'max:50'],
            'id_torniquete' => ['nullable', 'string', 'max:40'],
        ], [], ['codigo' => 'código']);
    }

    private function respuesta(\App\Services\Acceso\ResultadoAcceso $resultado)
    {
        return response()->json([
            'permitido' => $resultado->permitido,
            'motivo'    => $resultado->motivo,
            'mensaje'   => $resultado->mensaje,
            'consumido' => $resultado->pasesConsumidos,
            'compra'    => $resultado->compra ? [
                'folio'           => $resultado->compra->folio,
                'visitante'       => $resultado->compra->cliente?->nombreCompleto(),
                'pases_total'     => $resultado->compra->pases_total,
                'pases_usados'    => $resultado->compra->pases_usados,
                'pases_restantes' => $resultado->compra->pasesDisponibles(),
                'fecha_visita'    => $resultado->compra->fecha_visita->format('d/m/Y'),
                'detalle'         => $resultado->compra->detalle
                    ->map(fn ($r) => ['concepto' => $r->rubro_nombre_snap, 'cantidad' => $r->cantidad])
                    ->values(),
            ] : null,
        ]);
    }

    public function bitacora(Request $request)
    {
        $accesos = Acceso::with(['compra.cliente', 'cuenta.usuario'])
            ->when($request->filled('metodo'),
                fn ($q) => $q->where('metodo', $request->string('metodo')))
            ->when($request->filled('resultado'),
                fn ($q) => $q->where('resultado', $request->string('resultado')))
            ->when($request->filled('torniquete'),
                fn ($q) => $q->where('id_torniquete', $request->string('torniquete')))
            ->when($request->filled('desde'),
                fn ($q) => $q->whereDate('escaneado_en', '>=', $request->date('desde')))
            ->when($request->filled('hasta'),
                fn ($q) => $q->whereDate('escaneado_en', '<=', $request->date('hasta')))
            ->orderByDesc('escaneado_en')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('accesos.bitacora', [
            'accesos'     => $accesos,
            'torniquetes' => Acceso::query()->distinct()->orderBy('id_torniquete')->pluck('id_torniquete'),
        ]);
    }
}
