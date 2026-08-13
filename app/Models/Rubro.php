<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rubro de cobro (brief §5.2).
 *
 * El precio vive aquí en CENTAVOS como entero (§4.1). Al comprar se congela
 * una copia en compra_detalle (§4.3), así que cambiar un precio no altera
 * compras pasadas.
 */
class Rubro extends Model
{
    use SoftDeletes;

    protected $table = 'rubros';

    protected $fillable = [
        'tipo',
        'descripcion',
        'id_nacionalidad',
        'id_subnacionalidad',
        'id_tipo_acceso',
        'precio_centavos',
        'vigente_desde',
        'vigente_hasta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_centavos' => 'integer',
            'vigente_desde'   => 'date',
            'vigente_hasta'   => 'date',
            'activo'          => 'boolean',
        ];
    }

    public function nacionalidad()
    {
        return $this->belongsTo(Nacionalidad::class, 'id_nacionalidad');
    }

    public function subnacionalidad()
    {
        return $this->belongsTo(Subnacionalidad::class, 'id_subnacionalidad');
    }

    public function tipoAcceso()
    {
        return $this->belongsTo(TipoAcceso::class, 'id_tipo_acceso');
    }

    public function esGratis(): bool
    {
        return optional($this->tipoAcceso)->esGratis() ?? false;
    }

    /**
     * Solo para mostrar. NUNCA guardes este valor: el importe se calcula y se
     * almacena siempre en centavos.
     */
    public function precioFormateado(): string
    {
        return '$' . number_format($this->precio_centavos / 100, 2);
    }

    /**
     * Rubros que pueden venderse en una fecha dada. El portal público debe
     * cotizar SIEMPRE contra este scope, nunca contra lo que mande el
     * navegador (§4.2).
     */
    public function scopeVigentes(Builder $query, ?string $fecha = null): Builder
    {
        $fecha ??= now()->toDateString();

        return $query->where('activo', true)
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(function (Builder $q) use ($fecha) {
                $q->whereNull('vigente_hasta')
                  ->orWhereDate('vigente_hasta', '>=', $fecha);
            });
    }
}
