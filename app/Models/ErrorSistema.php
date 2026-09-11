<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * Un fallo del sistema, agrupado por huella.
 *
 * Se llama `ErrorSistema` y no `Error` porque `Error` es una clase interna de
 * PHP y colisionaría.
 */
class ErrorSistema extends Model
{
    protected $table = 'errores';
    protected $primaryKey = 'id_error';

    /**
     * `ultima_vez` y `ocurrencias` se llevan a mano; no son created/updated.
     */
    public $timestamps = false;

    protected $fillable = [
        'huella', 'clase', 'mensaje', 'archivo', 'linea',
        'codigo', 'metodo', 'url',
        'id_cuenta', 'id_cliente', 'ip', 'navegador', 'traza',
        'ocurrencias', 'primera_vez', 'ultima_vez',
        'atendido_en', 'atendido_por',
    ];

    protected function casts(): array
    {
        return [
            'linea'       => 'integer',
            'codigo'      => 'integer',
            'ocurrencias' => 'integer',
            'primera_vez' => 'datetime',
            'ultima_vez'  => 'datetime',
            'atendido_en' => 'datetime',
        ];
    }

    /**
     * Agrupa por clase, archivo y línea — no por mensaje.
     *
     * El mensaje suele traer datos variables («No query results for model
     * [Rubro] 47»), así que agrupar por él dejaría una fila por cada id y
     * anularía el propósito de agrupar.
     */
    public static function huellaDe(Throwable $e): string
    {
        return hash('sha256', implode('|', [
            $e::class,
            $e->getFile(),
            $e->getLine(),
        ]));
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'atendido_por');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function estaAtendido(): bool
    {
        return $this->atendido_en !== null;
    }

    /** Lo pendiente primero, y dentro de eso lo más reciente. */
    public function scopePendientes($query)
    {
        return $query->whereNull('atendido_en');
    }

    /** Nombre corto de la clase, que es lo que se lee en la lista. */
    public function claseCorta(): string
    {
        return class_basename($this->clase);
    }
}
