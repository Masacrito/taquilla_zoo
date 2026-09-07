<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reagenda extends Model
{
    protected $table = 'reagendas';
    public $timestamps = false;

    protected $fillable = ['id_compra', 'fecha_anterior', 'fecha_nueva', 'motivo', 'created_at'];

    protected function casts(): array
    {
        return [
            'fecha_anterior' => 'date',
            'fecha_nueva'    => 'date',
            'created_at'     => 'datetime',
        ];
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra');
    }
}
