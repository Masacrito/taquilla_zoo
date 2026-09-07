<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de un escaneo en el acceso (brief §5.6).
 *
 * Se guardan TODOS los escaneos, permitidos y rechazados. La tabla es de
 * solo inserción, como `movimientos`.
 */
class Acceso extends Model
{
    public const PERMITIDO = 'permitido';
    public const RECHAZADO = 'rechazado';

    /** Se leyó el QR: la firma HMAC garantiza que el pase es auténtico. */
    public const METODO_QR = 'qr';

    /** El operador capturó el folio a mano (contingencia, cámara caída). */
    public const METODO_FOLIO = 'folio';

    protected $table = 'accesos';
    public $timestamps = false;

    protected $fillable = [
        'id_compra', 'id_torniquete', 'metodo', 'pases_consumidos', 'escaneado_en',
        'id_cuenta', 'resultado', 'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'pases_consumidos' => 'integer',
            'escaneado_en'     => 'datetime',
        ];
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra');
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta', 'id_cuenta');
    }

    public function fuePermitido(): bool
    {
        return $this->resultado === self::PERMITIDO;
    }
}
