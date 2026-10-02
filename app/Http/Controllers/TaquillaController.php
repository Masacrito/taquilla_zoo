<?php

namespace App\Http\Controllers;

use App\Services\Reporte\TableroService;

class TaquillaController extends Controller
{
    public function dashboard(TableroService $tablero)
    {
        return view('taquilla.dashboard', [
            'tablero' => $tablero->paraTaquilla(),
        ]);
    }
}
