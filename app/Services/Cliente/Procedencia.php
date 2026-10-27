<?php

namespace App\Services\Cliente;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Pais;
use Illuminate\Validation\ValidationException;

/**
 * De dónde viene el visitante: país, estado y municipio (2026-10-05).
 *
 * Es obligatoria porque alimenta las estadísticas, pero solo hasta donde el
 * catálogo alcanza:
 *
 *   - El país se pide siempre.
 *   - El estado solo si el país es México: no hay estados de otros países.
 *   - El municipio solo si ese estado tiene municipios cargados (hoy, Chiapas).
 *
 * Lo que no aplica se devuelve en null aunque el navegador lo haya mandado,
 * para no guardar combinaciones imposibles («Francia, Chiapas»). La regla la
 * comparten el registro, el alta con Google y la compra; los
 * selectores (componente `x-procedencia`) solo la reflejan.
 */
class Procedencia
{
    /**
     * @return array{id_pais:int, id_estado:?int, id_municipio:?int}
     *
     * @throws ValidationException
     */
    public function exigir(array $datos): array
    {
        $pais = Pais::activos()->find($this->entero($datos['id_pais'] ?? null));

        if (! $pais) {
            throw ValidationException::withMessages([
                'id_pais' => 'Indica el país de procedencia.',
            ]);
        }

        $procedencia = ['id_pais' => $pais->id, 'id_estado' => null, 'id_municipio' => null];

        if (! $pais->esMexico()) {
            return $procedencia;
        }

        $estado = Estado::activos()->find($this->entero($datos['id_estado'] ?? null));

        if (! $estado) {
            throw ValidationException::withMessages([
                'id_estado' => 'Indica el estado de procedencia.',
            ]);
        }

        $procedencia['id_estado'] = $estado->id;

        $municipios = Municipio::activos()->where('id_estado', $estado->id);

        if (! $municipios->exists()) {
            return $procedencia;
        }

        $idMunicipio = $this->entero($datos['id_municipio'] ?? null);

        if ($idMunicipio === null || ! $municipios->whereKey($idMunicipio)->exists()) {
            throw ValidationException::withMessages([
                'id_municipio' => "Indica el municipio de {$estado->nombre}.",
            ]);
        }

        $procedencia['id_municipio'] = $idMunicipio;

        return $procedencia;
    }

    private function entero(mixed $valor): ?int
    {
        return ($valor === null || $valor === '') ? null : (int) $valor;
    }
}
