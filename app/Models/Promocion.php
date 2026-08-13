<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prevista en el anteproyecto, sin operación todavía (brief §5.1).
 * El brief §11 prohíbe agregar promociones o descuentos: los precios son fijos.
 */
class Promocion extends Model
{
    protected $table = 'promociones';

    protected $fillable = ['nombre', 'vigente_desde', 'vigente_hasta', 'activo'];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
            'activo'        => 'boolean',
        ];
    }
}
