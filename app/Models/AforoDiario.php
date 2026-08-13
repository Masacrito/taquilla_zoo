<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cupo por día (brief §5.6).
 *
 * OJO: los métodos de reserva y liberación usan UPDATE condicional atómico
 * (brief §4.5). No los reescribas como SELECT + save(): dos compras
 * simultáneas sobrevenderían el cupo.
 */
class AforoDiario extends Model
{
    protected $table = 'aforo_diario';
    protected $primaryKey = 'fecha';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['fecha', 'cupo_maximo', 'reservados', 'cerrado', 'motivo_cierre'];

    protected function casts(): array
    {
        return [
            'cupo_maximo' => 'integer',
            'reservados'  => 'integer',
            'cerrado'     => 'boolean',
        ];
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

    /** Cupo por omisión, configurable mientras el área operativa lo define (§12). */
    public static function cupoPorOmision(): int
    {
        return (int) config('taquilla.aforo_cupo_maximo');
    }

    public function disponibles(): int
    {
        return max(0, $this->cupo_maximo - $this->reservados);
    }

    /**
     * Reserva $pases de forma atómica. Devuelve false si no había lugar,
     * si el día está cerrado o si el día no existe.
     */
    public static function reservar(string $fecha, int $pases): bool
    {
        $filas = DB::table('aforo_diario')
            ->where('fecha', $fecha)
            ->where('cerrado', false)
            ->whereRaw('reservados + ? <= cupo_maximo', [$pases])
            ->update([
                'reservados' => DB::raw("reservados + {$pases}"),
                'updated_at' => now(),
            ]);

        return $filas > 0;
    }

    /** Devuelve pases al cupo (compra expirada o cancelada). */
    public static function liberar(string $fecha, int $pases): bool
    {
        $filas = DB::table('aforo_diario')
            ->where('fecha', $fecha)
            ->whereRaw('reservados - ? >= 0', [$pases])
            ->update([
                'reservados' => DB::raw("reservados - {$pases}"),
                'updated_at' => now(),
            ]);

        return $filas > 0;
    }
}
