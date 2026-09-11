<?php

use App\Jobs\ExpirarComprasPendientes;
use App\Jobs\PurgarErroresAtendidos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Requiere que el scheduler esté corriendo en el servidor:
|   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Compras sin pagar a los 15 minutos → expiradas (brief §5.7). No liberan
// cupo porque no hay cupo, pero sin esto un carrito abandonado se queda como
// `pendiente_pago` para siempre y ensucia cortes y conciliación.
Schedule::job(new ExpirarComprasPendientes())
    ->everyMinute()
    ->withoutOverlapping()
    ->name('expirar-compras-pendientes');

// Fallos ya atendidos más viejos que el periodo de retención. Una vez al día
// de madrugada: no corre prisa, y la tabla crece despacio.
//
// Solo borra los ATENDIDOS. Un fallo pendiente se conserva por viejo que sea:
// borrarlo sería perderlo sin que nadie lo hubiera visto.
Schedule::job(new PurgarErroresAtendidos())
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->name('purgar-errores-atendidos');
