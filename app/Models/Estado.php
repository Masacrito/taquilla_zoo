<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = 'estados';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function municipios()
    {
        return $this->hasMany(Municipio::class, 'id_estado');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
