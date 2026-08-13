<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoAcceso extends Model
{
    /** Nombre canónico del acceso sin costo. Ver Rubro::esGratis(). */
    public const GRATIS = 'GRATIS';

    public const PAGO_NORMAL = 'PAGO NORMAL';

    protected $table = 'tipos_acceso';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function rubros()
    {
        return $this->hasMany(Rubro::class, 'id_tipo_acceso');
    }

    public function esGratis(): bool
    {
        return mb_strtoupper(trim($this->nombre)) === self::GRATIS;
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
