<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nacionalidad extends Model
{
    protected $table = 'nacionalidades';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function rubros()
    {
        return $this->hasMany(Rubro::class, 'id_nacionalidad');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
