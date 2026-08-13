<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    public const INICIADO    = 'iniciado';
    public const APROBADO    = 'aprobado';
    public const RECHAZADO   = 'rechazado';
    public const REEMBOLSADO = 'reembolsado';

    protected $table = 'pagos';

    protected $fillable = [
        'id_compra', 'proveedor', 'referencia_externa', 'monto_centavos',
        'estado', 'autorizacion', 'payload_webhook', 'conciliado_en',
    ];

    protected function casts(): array
    {
        return [
            'monto_centavos'  => 'integer',
            'payload_webhook' => 'array',
            'conciliado_en'   => 'datetime',
        ];
    }

    protected $hidden = ['payload_webhook'];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra');
    }
}
