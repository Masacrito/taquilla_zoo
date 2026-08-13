<?php

namespace App\Http\Controllers;

use App\Models\Rubro;

class PortalController extends Controller
{
    public function inicio()
    {
        return view('publico.inicio', [
            'rubros' => Rubro::vigentes()->with('tipoAcceso')->orderBy('precio_centavos')->get(),
        ]);
    }
}
