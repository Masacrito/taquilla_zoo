<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Código de verificación de correo (brief §5.3).
 *
 * Se almacena el hash del código, nunca el código en claro.
 */
class VerificacionCorreo extends Model
{
    public const MINUTOS_VIGENCIA = 10;
    public const MAX_REENVIOS     = 3;
    public const MAX_INTENTOS     = 5;

    protected $table = 'verificaciones_correo';
    public $timestamps = false;

    protected $fillable = ['correo', 'codigo_hash', 'intentos', 'expira_en', 'consumido_en', 'created_at'];

    protected $hidden = ['codigo_hash'];

    protected function casts(): array
    {
        return [
            'intentos'     => 'integer',
            'expira_en'    => 'datetime',
            'consumido_en' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function vigente(): bool
    {
        return $this->consumido_en === null && $this->expira_en->isFuture();
    }

    public function coincide(string $codigo): bool
    {
        return Hash::check($codigo, $this->codigo_hash);
    }
}
