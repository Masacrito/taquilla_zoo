<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Calendario de operación: qué días abre el zoológico.
 *
 * Aquí NO hay cupo. No existe aforo máximo ni mínimo, así que ninguna compra
 * reserva ni libera lugares: la única pregunta que responde esta tabla es si
 * la fecha está abierta.
 */
class AforoDiario extends Model
{
    protected $table = 'aforo_diario';
    protected $primaryKey = 'fecha';
    public $incrementing = false;
    protected $keyType = 'string';

    /** Quién abrió el día: una persona desde el panel, o la tarea diaria. */
    public const MANUAL     = 'manual';
    public const AUTOMATICO = 'automatico';

    protected $fillable = ['fecha', 'cerrado', 'motivo_cierre', 'origen', 'revisado_en'];

    protected function casts(): array
    {
        return [
            'cerrado'     => 'boolean',
            'revisado_en' => 'datetime',
        ];
    }

    public function esAutomatico(): bool
    {
        return $this->origen === self::AUTOMATICO;
    }

    /** El mismo criterio que scopePendientesDeRevision, para un día ya cargado. */
    public function pendienteDeRevision(): bool
    {
        return $this->esAutomatico()
            && $this->revisado_en === null
            && $this->fecha->gte(Carbon::today());
    }

    /**
     * `fecha` es la llave primaria, así que su representación tiene que ser
     * idéntica en toda consulta.
     *
     * No se usa el cast 'date' a propósito: al guardar, Laravel serializaría
     * con el formato de fecha del modelo ('Y-m-d H:i:s'). PostgreSQL lo
     * coacciona a DATE sin quejarse, pero SQLite —que usan las pruebas— lo
     * guarda literal como '2026-08-18 00:00:00', y a partir de ahí ningún
     * where('fecha', '2026-08-18') vuelve a encontrar el registro.
     */
    protected function fecha(): Attribute
    {
        return Attribute::make(
            get: fn ($valor) => $valor instanceof Carbon ? $valor : Carbon::parse($valor),
            set: fn ($valor) => Carbon::parse($valor)->toDateString(),
        );
    }

    /** El ZooMAT no abre los lunes (brief §5.6). */
    public static function esLunes(string|Carbon $fecha): bool
    {
        return Carbon::parse($fecha)->isMonday();
    }

    /**
     * Días que generó el sistema y que ningún administrador ha mirado.
     * Solo de hoy en adelante: revisar un día que ya pasó no sirve de nada.
     */
    public function scopePendientesDeRevision($query)
    {
        return $query->where('origen', self::AUTOMATICO)
            ->whereNull('revisado_en')
            ->where('fecha', '>=', now()->toDateString());
    }

    /** Días abiertos de hoy en adelante, para pintar el calendario de compra. */
    public function scopeAbiertosDesdeHoy($query)
    {
        return $query->where('fecha', '>=', now()->toDateString())
            ->where('cerrado', false);
    }
}
