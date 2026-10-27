<?php

namespace App\Jobs;

use App\Mail\RecordatorioVisita;
use App\Models\AforoDiario;
use App\Models\Compra;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recuerda la visita a quien tiene boletos pagados y sin usar (2026-10-05).
 *
 * Hay dos avisos (routes/console.php):
 *
 *   - `vispera`:   una vez, la tarde anterior, para las visitas de mañana.
 *   - `mismo_dia`: cada 15 minutos entre la hora configurada y el corte de
 *     venta, para las de hoy (2026-10-27). Así también lo recibe quien
 *     compra por la mañana para el mismo día.
 *
 * Reglas:
 *   - Solo compras `pagada`: si ya entró parte del grupo, están en el
 *     zoológico y el aviso sobra.
 *   - No se recuerda una compra de hace menos de una hora (2026-10-27; antes
 *     era «de hoy»): esa persona acaba de recibir su comprobante. Se mide
 *     con `fecha_compra`, que el pago reinicia y queda a menos de 15 minutos
 *     del cobro. Quien compra para hoy pasadas las 14:30 ya no lo alcanza;
 *     su comprobante trae el aviso de que la visita es hoy.
 *   - Si el día se cerró después de vender (contingencia), no se avisa: decir
 *     «te esperamos» sería mentir.
 *
 * La columna `recordatorio_*_en` se marca con un UPDATE condicional ANTES de
 * encolar el correo: si la tarea corriera dos veces, la segunda no encuentra
 * nada que marcar y no repite el envío.
 */
class EnviarRecordatoriosDeVisita implements ShouldQueue
{
    use Queueable;

    public const VISPERA   = 'vispera';
    public const MISMO_DIA = 'mismo_dia';

    /** Tiempo desde la compra antes de recordarla. */
    public const MINUTOS_DE_GRACIA = 60;

    private const COLUMNA = [
        self::VISPERA   => 'recordatorio_vispera_en',
        self::MISMO_DIA => 'recordatorio_dia_en',
    ];

    public function __construct(public readonly string $tipo)
    {
    }

    public function handle(): void
    {
        if (! config('taquilla.recordatorios.activos')) {
            return;
        }

        $columna = self::COLUMNA[$this->tipo];
        $visita  = ($this->tipo === self::VISPERA ? now()->addDay() : now())->toDateString();

        if (AforoDiario::where('fecha', $visita)->where('cerrado', false)->doesntExist()) {
            return;
        }

        $compras = Compra::with('cliente')
            ->where('estado', Compra::PAGADA)
            ->whereDate('fecha_visita', $visita)
            ->where('fecha_compra', '<=', now()->subMinutes(self::MINUTOS_DE_GRACIA))
            ->whereNull($columna)
            ->get();

        $enviados = 0;

        foreach ($compras as $compra) {
            $marcada = Compra::whereKey($compra->id)->whereNull($columna)->update([$columna => now()]);

            if ($marcada === 0) {
                continue;
            }

            Mail::to($compra->correoDestino())->queue(new RecordatorioVisita($compra, $this->tipo));
            $enviados++;
        }

        if ($enviados > 0) {
            Log::info("Recordatorios de visita ({$this->tipo}) para el {$visita}: {$enviados}");
        }
    }
}
