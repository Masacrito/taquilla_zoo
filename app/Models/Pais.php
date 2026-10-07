<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    protected $table = 'paises';

    /** El único país cuyos estados y municipios maneja el catálogo. */
    public const ISO_MEXICO = 'MEX';

    protected $fillable = ['nombre', 'iso', 'activo'];

    public function esMexico(): bool
    {
        return $this->iso === self::ISO_MEXICO;
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
