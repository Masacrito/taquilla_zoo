<?php

namespace App\Http\Controllers;

use App\Models\Rubro;
use App\Services\Operacion\EstadoOperacionService;

class PortalController extends Controller
{
    public function inicio(EstadoOperacionService $estado)
    {
        return view('publico.inicio', [
            'rubros' => Rubro::vigentes()->with('tipoAcceso')->orderBy('precio_centavos')->get(),
            'estado' => $estado->ahora(),
        ]);
    }

    /**
     * Políticas de entrega, cancelación y reembolso. Pública sin condición:
     * el banco la revisa sin sesión (guía KYC e-commerce, pregunta 7).
     */
    public function politicas()
    {
        return view('publico.politicas', ['politicas' => config('politicas')]);
    }
}
