<?php

namespace App\Services\Venta;

use App\Models\Municipio;
use App\Models\Rubro;
use Illuminate\Validation\ValidationException;

/**
 * Calcula el total de una compra (brief §4.2).
 *
 * ═══════════════════════════════════════════════════════════════════════
 *  EL PRECIO JAMÁS VIENE DEL NAVEGADOR.
 *
 *  De la petición solo se aceptan: id_rubro, cantidades y procedencia.
 *  Cualquier campo `precio`, `importe` o `total` que llegue en el request
 *  se ignora por completo. El total se arma leyendo `rubros` vigentes a la
 *  fecha de visita.
 * ═══════════════════════════════════════════════════════════════════════
 */
class CotizarCompraService
{
    /**
     * @param  array<int, array{id_rubro:mixed, cant_hombre?:mixed, cant_mujer?:mixed, id_pais?:mixed, id_estado?:mixed, id_municipio?:mixed}>  $renglones
     *
     * @throws ValidationException
     */
    public function cotizar(array $renglones, string $fechaVisita): Cotizacion
    {
        if ($renglones === []) {
            throw ValidationException::withMessages([
                'renglones' => 'Debes elegir al menos un boleto.',
            ]);
        }

        // Un solo SELECT para todos los rubros pedidos, filtrado por vigencia
        // a la fecha de visita.
        $idsPedidos = collect($renglones)->pluck('id_rubro')->filter()->map(fn ($v) => (int) $v)->unique();

        $rubros = Rubro::vigentes($fechaVisita)
            ->whereIn('id', $idsPedidos)
            ->get()
            ->keyBy('id');

        $cotizados = [];
        $total     = 0;
        $pases     = 0;

        foreach ($renglones as $indice => $renglon) {
            $idRubro = (int) ($renglon['id_rubro'] ?? 0);
            $rubro   = $rubros->get($idRubro);

            if (! $rubro) {
                throw ValidationException::withMessages([
                    "renglones.{$indice}.id_rubro" => 'Ese boleto no está disponible para la fecha elegida.',
                ]);
            }

            $cantHombre = max(0, (int) ($renglon['cant_hombre'] ?? 0));
            $cantMujer  = max(0, (int) ($renglon['cant_mujer'] ?? 0));
            $cantidad   = $cantHombre + $cantMujer;

            if ($cantidad === 0) {
                continue;   // renglón vacío: se descarta en silencio
            }

            $this->validarProcedencia($renglon, $indice);

            // Aritmética entera pura: centavos × cantidad. Nunca float.
            $importe = $rubro->precio_centavos * $cantidad;

            $cotizados[] = new RenglonCotizado(
                rubro:           $rubro,
                nombre:          $rubro->tipo,
                precioCentavos:  $rubro->precio_centavos,
                cantHombre:      $cantHombre,
                cantMujer:       $cantMujer,
                cantidad:        $cantidad,
                importeCentavos: $importe,
                idPais:          $this->entero($renglon['id_pais'] ?? null),
                idEstado:        $this->entero($renglon['id_estado'] ?? null),
                idMunicipio:     $this->entero($renglon['id_municipio'] ?? null),
            );

            $total += $importe;
            $pases += $cantidad;
        }

        if ($cotizados === []) {
            throw ValidationException::withMessages([
                'renglones' => 'Debes indicar al menos una persona.',
            ]);
        }

        return new Cotizacion($cotizados, $total, $pases);
    }

    /**
     * El municipio solo aplica si pertenece al estado indicado. Sin esto se
     * podrían guardar combinaciones imposibles que ensucian las estadísticas.
     */
    private function validarProcedencia(array $renglon, int|string $indice): void
    {
        $idEstado    = $this->entero($renglon['id_estado'] ?? null);
        $idMunicipio = $this->entero($renglon['id_municipio'] ?? null);

        if ($idMunicipio === null) {
            return;
        }

        if ($idEstado === null) {
            throw ValidationException::withMessages([
                "renglones.{$indice}.id_municipio" => 'Indica el estado antes que el municipio.',
            ]);
        }

        $pertenece = Municipio::where('id', $idMunicipio)
            ->where('id_estado', $idEstado)
            ->exists();

        if (! $pertenece) {
            throw ValidationException::withMessages([
                "renglones.{$indice}.id_municipio" => 'Ese municipio no pertenece al estado elegido.',
            ]);
        }
    }

    private function entero(mixed $valor): ?int
    {
        return ($valor === null || $valor === '') ? null : (int) $valor;
    }
}
