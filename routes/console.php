<?php

use App\Jobs\ExpirarComprasPendientes;
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

// Compras sin pagar a los 15 minutos → expiradas, y su aforo se libera
// (brief §5.7). Cada minuto: el retraso máximo en devolver un lugar al cupo
// es de un minuto.
Schedule::job(new ExpirarComprasPendientes())
    ->everyMinute()
    ->withoutOverlapping()
    ->name('expirar-compras-pendientes');
