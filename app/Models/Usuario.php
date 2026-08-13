<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Usuario extends Model
{
    use SoftDeletes;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'nombre', 'puesto', 'extension', 'email', 'id_departamento',
    ];

    public function cuenta()
    {
        return $this->hasOne(Cuenta::class, 'id_usuario');
    }
}
