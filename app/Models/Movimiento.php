<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'movimientos';
    protected $primaryKey = 'id_movimiento';
    public $timestamps = false;

    protected $fillable = [
        'id_cuenta', 'tabla', 'accion', 'registro_id', 'detalles', 'fecha',
    ];

    protected $casts = [
        'detalles' => 'array',
        'fecha'    => 'datetime',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta');
    }

    public static function registrar(
        ?int $idCuenta,
        string $tabla,
        string $accion,
        ?string $registroId = null,
        ?array $detalles = null
    ): self {
        return self::create([
            'id_cuenta'   => $idCuenta,
            'tabla'       => $tabla,
            'accion'      => $accion,
            'registro_id' => $registroId,
            'detalles'    => $detalles,
            'fecha'       => now(),
        ]);
    }
}
