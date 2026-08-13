<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Nacionalidad;
use App\Models\Pais;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD genérico de los catálogos simples (brief §5.1).
 *
 * Son seis tablas con la misma forma (nombre + activo), así que en vez de
 * seis controladores idénticos hay uno solo parametrizado por slug. Los
 * catálogos NO se borran: se desactivan, porque compras históricas los
 * referencian.
 */
class CatalogoController extends Controller
{
    /**
     * slug => [modelo, etiqueta singular, etiqueta plural, campos extra]
     */
    private const CATALOGOS = [
        'paises' => [Pais::class, 'País', 'Países'],
        'estados' => [Estado::class, 'Estado', 'Estados'],
        'municipios' => [Municipio::class, 'Municipio', 'Municipios'],
        'nacionalidades' => [Nacionalidad::class, 'Nacionalidad', 'Nacionalidades'],
        'subnacionalidades' => [Subnacionalidad::class, 'Subnacionalidad', 'Subnacionalidades'],
        'tipos-acceso' => [TipoAcceso::class, 'Tipo de acceso', 'Tipos de acceso'],
    ];

    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function index(string $catalogo = 'paises')
    {
        [$modelo, , $plural] = $this->resolver($catalogo);

        $query = $modelo::query()->orderBy('nombre');

        if ($catalogo === 'municipios') {
            $query->with('estado');
        }

        return view('admin.catalogos.index', [
            'catalogo'  => $catalogo,
            'plural'    => $plural,
            'registros' => $query->paginate(50)->withQueryString(),
            'catalogos' => $this->menu(),
            'estados'   => $catalogo === 'municipios' ? Estado::orderBy('nombre')->get() : collect(),
        ]);
    }

    public function store(Request $request, string $catalogo)
    {
        [$modelo, $singular] = $this->resolver($catalogo);
        $tabla = (new $modelo)->getTable();

        $datos = $request->validate(
            $this->reglas($catalogo, $tabla),
            [],
            ['nombre' => 'nombre', 'iso' => 'clave ISO', 'id_estado' => 'estado'],
        );

        $datos['activo'] = true;

        $registro = $modelo::create($datos);

        $this->bitacora->creado($registro);

        return back()->with('success', "{$singular} «{$registro->nombre}» agregado.");
    }

    public function update(Request $request, string $catalogo, int $id)
    {
        [$modelo, $singular] = $this->resolver($catalogo);
        $registro = $modelo::findOrFail($id);
        $tabla = $registro->getTable();

        $datos = $request->validate(
            $this->reglas($catalogo, $tabla, $id),
            [],
            ['nombre' => 'nombre', 'iso' => 'clave ISO', 'id_estado' => 'estado'],
        );

        $anterior = $registro->getOriginal();
        $registro->fill($datos)->save();

        $this->bitacora->actualizado($registro, $anterior);

        return back()->with('success', "{$singular} «{$registro->nombre}» actualizado.");
    }

    /**
     * Activa o desactiva. No hay borrado: un catálogo desactivado deja de
     * ofrecerse en los formularios pero sigue explicando registros pasados.
     */
    public function toggle(string $catalogo, int $id)
    {
        [$modelo, $singular] = $this->resolver($catalogo);
        $registro = $modelo::findOrFail($id);

        $anterior = $registro->getOriginal();
        $registro->activo = ! $registro->activo;
        $registro->save();

        $this->bitacora->actualizado($registro, $anterior);

        return back()->with('success', $registro->activo
            ? "{$singular} «{$registro->nombre}» activado."
            : "{$singular} «{$registro->nombre}» desactivado.");
    }

    // === Apoyo ===

    private function resolver(string $catalogo): array
    {
        abort_unless(isset(self::CATALOGOS[$catalogo]), 404, 'Catálogo desconocido.');

        return self::CATALOGOS[$catalogo];
    }

    private function menu(): array
    {
        return collect(self::CATALOGOS)
            ->map(fn ($def, $slug) => ['slug' => $slug, 'plural' => $def[2]])
            ->values()
            ->all();
    }

    private function reglas(string $catalogo, string $tabla, ?int $ignorar = null): array
    {
        $unico = Rule::unique($tabla, 'nombre');
        if ($ignorar !== null) {
            $unico->ignore($ignorar);
        }

        $reglas = ['nombre' => ['required', 'string', 'max:120']];

        if ($catalogo === 'municipios') {
            // El nombre solo tiene que ser único dentro de su estado.
            $reglas['id_estado'] = ['required', 'exists:estados,id'];
            $reglas['nombre'][] = 'max:120';
        } else {
            $reglas['nombre'][] = $unico;
        }

        if ($catalogo === 'paises') {
            $isoUnico = Rule::unique($tabla, 'iso');
            if ($ignorar !== null) {
                $isoUnico->ignore($ignorar);
            }
            $reglas['iso'] = ['nullable', 'string', 'size:3', $isoUnico];
        }

        return $reglas;
    }
}
