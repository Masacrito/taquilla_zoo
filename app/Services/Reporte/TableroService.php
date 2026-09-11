<?php

namespace App\Services\Reporte;

use App\Models\Acceso;
use App\Models\AforoDiario;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\ErrorSistema;
use App\Models\Rubro;
use Illuminate\Support\Carbon;

/**
 * Arma el tablero del panel administrativo.
 *
 * La idea de diseño: un tablero que siempre enseña las mismas tarjetas en
 * verde se vuelve invisible en una semana. Por eso los avisos son
 * CONDICIONALES —solo existen cuando hay algo que atender— y lo demás son
 * números del día, no adornos.
 *
 * No inventa métricas. Todo sale de datos que el sistema ya tiene: si no se
 * puede calcular con lo que hay, no aparece.
 */
class TableroService
{
    /** Con menos días abiertos por delante, se avisa. */
    private const DIAS_CALENDARIO_MINIMOS = 15;

    public function __construct(private readonly CorteIngresosService $cortes)
    {
    }

    public function generar(): array
    {
        $hoy = now()->toDateString();

        return [
            'avisos'   => $this->avisos(),
            'hoy'      => $this->hoy($hoy),
            'semana'   => $this->semana($hoy),
        ];
    }

    /**
     * Lo que requiere que alguien haga algo.
     *
     * Cada aviso es un problema que hoy NADIE detecta hasta que un visitante
     * se queja. El del calendario es el más traicionero: cuando se acaban los
     * días generados, /comprar deja de vender y no hay ningún síntoma en el
     * panel.
     *
     * @return array<int, array{tipo: string, texto: string, ruta: ?string}>
     */
    private function avisos(): array
    {
        $avisos = [];

        // ── Fallos del sistema sin revisar ──
        $fallos = ErrorSistema::pendientes()->count();

        if ($fallos > 0) {
            $avisos[] = [
                'tipo'  => 'alerta',
                'texto' => $fallos === 1
                    ? 'Hay 1 fallo del sistema sin revisar.'
                    : "Hay {$fallos} fallos del sistema sin revisar.",
                'ruta'  => route('admin.errores.index'),
            ];
        }

        // ── El calendario se agota ──
        $diasAbiertos = AforoDiario::abiertosDesdeHoy()->count();

        if ($diasAbiertos === 0) {
            $avisos[] = [
                'tipo'  => 'alerta',
                'texto' => 'No hay días abiertos en el calendario: nadie puede comprar boletos.',
                'ruta'  => route('admin.aforo.index'),
            ];
        } elseif ($diasAbiertos <= self::DIAS_CALENDARIO_MINIMOS) {
            $avisos[] = [
                'tipo'  => 'atencion',
                'texto' => "Solo quedan {$diasAbiertos} días abiertos en el calendario. Genera más antes de que se agoten.",
                'ruta'  => route('admin.aforo.index'),
            ];
        }

        // ── Sin tarifas no se vende ──
        if (Rubro::vigentes()->count() === 0) {
            $avisos[] = [
                'tipo'  => 'alerta',
                'texto' => 'No hay tarifas vigentes: el portal no puede vender.',
                'ruta'  => route('admin.rubros.index'),
            ];
        }

        // ── Los avisos de fallo no le llegan a nadie ──
        $correoSuper = Cuenta::superAdmin()?->usuario?->email;

        if (blank($correoSuper) || str_ends_with((string) $correoSuper, '@example.com')) {
            $avisos[] = [
                'tipo'  => 'atencion',
                'texto' => 'El Super Admin no tiene un correo real: los avisos de fallos del sistema no llegan a nadie.',
                'ruta'  => route('admin.users.index'),
            ];
        }

        // ── Compras esperando pago ──
        $pendientes = Compra::where('estado', Compra::PENDIENTE_PAGO)->count();

        if ($pendientes > 0) {
            $avisos[] = [
                'tipo'  => 'info',
                'texto' => $pendientes === 1
                    ? '1 compra esperando confirmación de pago.'
                    : "{$pendientes} compras esperando confirmación de pago.",
                'ruta'  => route('admin.compras.index'),
            ];
        }

        return $avisos;
    }

    /**
     * El día de hoy, con una distinción que importa en la operación.
     *
     * «Vendido hoy» y «gente que viene hoy» son cosas distintas: alguien puede
     * comprar el martes para visitar el sábado. Para la caja importa lo
     * primero; para saber cuánta gente esperar en la puerta, lo segundo.
     */
    private function hoy(string $hoy): array
    {
        // Lo cobrado hoy, por fecha de COMPRA.
        $venta = $this->cortes->generar($hoy, $hoy, CorteIngresosService::POR_COMPRA)['resumen'];

        // Lo programado para hoy, por fecha de VISITA.
        $visita = $this->cortes->generar($hoy, $hoy, CorteIngresosService::POR_VISITA)['resumen'];

        // Quién ya cruzó el torniquete hoy.
        $entraron = (int) Acceso::whereDate('escaneado_en', $hoy)
            ->where('resultado', Acceso::PERMITIDO)
            ->sum('pases_consumidos');

        return [
            'vendido_centavos' => $venta['total_centavos'],
            'pases_vendidos'   => $venta['pases'],
            'compras'          => $venta['compras'],
            'pases_esperados'  => $visita['pases'],
            'ya_entraron'      => $entraron,
        ];
    }

    /**
     * Ingresos de los últimos siete días.
     *
     * Sale de `por_dia`, que el corte ya calcula: no hay consulta nueva. Se
     * pinta con barras de CSS, sin librería de gráficas — el brief no las
     * permite y para siete barras tampoco hacen falta.
     */
    private function semana(string $hoy): array
    {
        $desde = Carbon::parse($hoy)->subDays(6)->toDateString();

        $porDia = collect($this->cortes->generar($desde, $hoy, CorteIngresosService::POR_COMPRA)['por_dia'])
            ->keyBy(fn ($fila) => (string) (is_array($fila) ? $fila['dia'] : $fila->dia));

        $dias = [];
        $tope = 0;

        for ($i = 6; $i >= 0; $i--) {
            $fecha = Carbon::parse($hoy)->subDays($i);
            $clave = $fecha->toDateString();
            $fila  = $porDia->get($clave);

            $centavos = (int) ($fila === null
                ? 0
                : (is_array($fila) ? ($fila['total_centavos'] ?? 0) : ($fila->total_centavos ?? 0)));

            $tope = max($tope, $centavos);

            $dias[] = [
                'fecha'    => $fecha,
                'centavos' => $centavos,
            ];
        }

        // El porcentaje se calcula aquí y no en la vista: la vista solo pinta.
        foreach ($dias as $i => $dia) {
            $dias[$i]['porcentaje'] = $tope > 0
                ? (int) round($dia['centavos'] / $tope * 100)
                : 0;
        }

        return [
            'dias'  => $dias,
            'total' => array_sum(array_column($dias, 'centavos')),
        ];
    }
}
