<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subnacionalidad extends Model
{
    protected $table = 'subnacionalidades';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function rubros()
    {
        return $this->hasMany(Rubro::class, 'id_subnacionalidad');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
