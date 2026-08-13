<?php

namespace App\Http\Controllers;

use App\Http\Requests\RubroRequest;
use App\Models\Nacionalidad;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Http\Request;

class RubroController extends Controller
{
    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function index()
    {
        $rubros = Rubro::with(['nacionalidad', 'subnacionalidad', 'tipoAcceso'])
            ->orderBy('tipo')
            ->get();

        return view('admin.rubros.index', [
            'rubros'            => $rubros,
            'nacionalidades'    => Nacionalidad::activos()->orderBy('nombre')->get(),
            'subnacionalidades' => Subnacionalidad::activos()->orderBy('nombre')->get(),
            'tiposAcceso'       => TipoAcceso::activos()->orderBy('nombre')->get(),
            'idTipoGratis'      => TipoAcceso::where('nombre', TipoAcceso::GRATIS)->value('id'),
        ]);
    }

    public function store(RubroRequest $request)
    {
        $rubro = Rubro::create($request->datosDelRubro());

        $this->bitacora->creado($rubro);

        return back()->with('success', "Rubro «{$rubro->tipo}» creado.");
    }

    public function update(RubroRequest $request, Rubro $rubro)
    {
        $anterior = $rubro->getOriginal();

        $rubro->fill($request->datosDelRubro())->save();

        $this->bitacora->actualizado($rubro, $anterior);

        return back()->with('success', "Rubro «{$rubro->tipo}» actualizado.");
    }

    /**
     * Alterna el rubro entre activo e inactivo. No lo borra: un rubro
     * inactivo deja de venderse pero sigue explicando compras pasadas.
     */
    public function toggle(Rubro $rubro)
    {
        $anterior = $rubro->getOriginal();

        $rubro->activo = ! $rubro->activo;
        $rubro->save();

        $this->bitacora->actualizado($rubro, $anterior);

        return back()->with('success', $rubro->activo
            ? "Rubro «{$rubro->tipo}» activado."
            : "Rubro «{$rubro->tipo}» desactivado.");
    }

    /**
     * Eliminación lógica (brief §4.6). El rubro sigue en la base porque
     * compras históricas lo referencian.
     */
    public function destroy(Rubro $rubro)
    {
        $snapshot = [
            'tipo'            => $rubro->tipo,
            'precio_centavos' => $rubro->precio_centavos,
        ];

        $rubro->delete();

        $this->bitacora->registrar('rubros', BitacoraService::DELETE, (string) $rubro->id, $snapshot);

        return back()->with('success', "Rubro «{$rubro->tipo}» eliminado.");
    }
}
