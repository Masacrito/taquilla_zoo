<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Renglón de una compra (brief §5.4).
 *
 * Guarda una COPIA del nombre y el precio del rubro (§4.3). No es
 * redundancia: es lo que permite que un corte de hace seis meses siga
 * cuadrando después de un aumento de tarifas.
 */
class CompraDetalle extends Model
{
    protected $table = 'compra_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_compra', 'id_rubro', 'rubro_nombre_snap', 'precio_centavos_snap',
        'cant_hombre', 'cant_mujer', 'cantidad', 'importe_centavos',
        'id_pais', 'id_estado', 'id_municipio',
        'id_nacionalidad', 'id_subnacionalidad', 'id_tipo_acceso',
    ];

    protected function casts(): array
    {
        return [
            'precio_centavos_snap' => 'integer',
            'cant_hombre'          => 'integer',
            'cant_mujer'           => 'integer',
            'cantidad'             => 'integer',
            'importe_centavos'     => 'integer',
        ];
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra');
    }

    public function rubro()
    {
        return $this->belongsTo(Rubro::class, 'id_rubro');
    }

    public function importeFormateado(): string
    {
        return '$' . number_format($this->importe_centavos / 100, 2);
    }
}
