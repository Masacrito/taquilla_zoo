<?php

namespace App\Models;

use App\Mail\RestablecerContrasena;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

/**
 * Visitante del portal público. Se autentica con el guard `cliente`.
 *
 * No tiene rol ni permisos a propósito: todos los clientes son iguales y
 * ninguno debe poder alcanzar el panel interno. Ver brief §3.2.
 */
class Cliente extends Authenticatable
{
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'correo',
        'password',
        'proveedor_oauth',
        'proveedor_oauth_id',
        'nombre',
        'apellidos',
        'fecha_nacimiento',
        'genero',
        'telefono',
        'id_pais',
        'id_estado',
        'id_municipio',
        'correo_verificado_en',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'proveedor_oauth_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento'     => 'date',
            'correo_verificado_en' => 'datetime',
            'password'             => 'hashed',
        ];
    }

    public function compras()
    {
        return $this->hasMany(Compra::class, 'id_cliente');
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->nombre} {$this->apellidos}");
    }

    public function correoVerificado(): bool
    {
        return $this->correo_verificado_en !== null;
    }

    /**
     * El broker de contraseñas busca `email`; aquí la columna es `correo`.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    /**
     * Enlace de «¿Olvidaste tu contraseña?» (2026-10-27). Correo propio en
     * lugar de la notificación en inglés que trae Laravel.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        Mail::to($this->correo)->queue(new RestablecerContrasena($token));
    }

    /**
     * Entró por OAuth y nunca definió contraseña local.
     */
    public function soloOauth(): bool
    {
        return $this->password === null && $this->proveedor_oauth !== null;
    }
}
