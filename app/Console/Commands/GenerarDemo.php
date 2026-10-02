<?php

namespace App\Console\Commands;

use App\Models\Compra;
use App\Services\Demo\GeneradorDemoService;
use Illuminate\Console\Command;

/**
 * Llena el sistema con datos de demostración, o los quita.
 *
 * Para ver cómo se comportan el tablero, los cortes y las estadísticas con
 * varios meses de operación antes de que exista esa operación. La historia
 * la arma GeneradorDemoService pasando por los servicios reales.
 *
 * Nunca en producción: mezcla compras de mentira con las de verdad y consume
 * folios. El comando se niega a correr ahí.
 */
class GenerarDemo extends Command
{
    protected $signature = 'taquilla:demo-generar
        {--meses=3 : Meses de historia hacia atrás}
        {--semilla=2026 : Semilla del azar; la misma semilla repite la misma historia}
        {--limpiar : Quita los datos de demostración en vez de generarlos}
        {--force : No pedir confirmación}';

    protected $description = 'Genera (o quita) datos de demostración recorriendo el flujo real de compra';

    public function handle(GeneradorDemoService $generador): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('  Este comando no corre en producción.');

            return self::FAILURE;
        }

        return $this->option('limpiar')
            ? $this->limpiar($generador)
            : $this->generar($generador);
    }

    private function generar(GeneradorDemoService $generador): int
    {
        $meses = max(1, min(12, (int) $this->option('meses')));

        $this->newLine();

        if ($generador->yaHayDatos()) {
            $this->warn('  Ya hay datos de demostración. Quítalos primero:');
            $this->line('    php artisan taquilla:demo-generar --limpiar');
            $this->newLine();

            return self::FAILURE;
        }

        $reales = Compra::withTrashed()->count();

        if ($reales > 0) {
            $this->warn("  La base ya tiene {$reales} compras que no son de demostración.");
            $this->warn('  Las de demostración se mezclarán con ellas y consumirán folios.');
        }

        $this->line("  Se generarán {$meses} meses de historia en la base «" . config('database.default') . '».');
        $this->line('  No se envía ningún correo.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('  ¿Continuar?', true)) {
            return self::SUCCESS;
        }

        $resumen = $generador->generar($meses, (int) $this->option('semilla'));

        $this->mostrar($resumen);
        $this->info('  Listo. Para quitarlos: php artisan taquilla:demo-generar --limpiar');
        $this->newLine();

        return self::SUCCESS;
    }

    private function limpiar(GeneradorDemoService $generador): int
    {
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('  ¿Quitar todos los datos de demostración?', true)) {
            return self::SUCCESS;
        }

        $this->mostrar($generador->limpiar());
        $this->info('  Datos de demostración eliminados.');
        $this->newLine();

        return self::SUCCESS;
    }

    /** @param array<string, int> $conteos */
    private function mostrar(array $conteos): void
    {
        $this->newLine();

        foreach ($conteos as $que => $cuantos) {
            $this->line('    ' . str_pad($que . ' ', 30, '.') . ' ' . number_format($cuantos));
        }

        $this->newLine();
    }
}
