<?php

use App\Jobs\EnviarRecordatoriosDeVisita;
use App\Jobs\ExpirarComprasPendientes;
use App\Jobs\GenerarCalendario;
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

// Mantiene abiertos los meses que el portal ofrece a la venta. Diario y no
// mensual a propósito: es idempotente, así que en la práctica solo crea días
// el 1 de cada mes, pero si el scheduler estuvo caído ese día se repone solo.
Schedule::job(new GenerarCalendario())
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->name('generar-calendario');

// Recordatorios de visita (2026-10-05): la tarde anterior, una vez, y el
// mismo día cada 15 minutos desde la hora configurada hasta el corte de venta
// (2026-10-27), para alcanzar también a quien compra esa mañana. Cada compra
// recibe cada aviso una sola vez; el job lo garantiza.
Schedule::job(new EnviarRecordatoriosDeVisita(EnviarRecordatoriosDeVisita::VISPERA))
    ->dailyAt(config('taquilla.recordatorios.vispera'))
    ->withoutOverlapping()
    ->name('recordatorios-vispera');

Schedule::job(new EnviarRecordatoriosDeVisita(EnviarRecordatoriosDeVisita::MISMO_DIA))
    ->everyFifteenMinutes()
    ->between(config('taquilla.recordatorios.mismo_dia'), config('taquilla.compra.hora_corte_mismo_dia'))
    ->withoutOverlapping()
    ->name('recordatorios-mismo-dia');

// Fallos ya atendidos más viejos que el periodo de retención. Una vez al día
// de madrugada: no corre prisa, y la tabla crece despacio.
//
// Solo borra los ATENDIDOS. Un fallo pendiente se conserva por viejo que sea:
// borrarlo sería perderlo sin que nadie lo hubiera visto.
Schedule::job(new PurgarErroresAtendidos())
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->name('purgar-errores-atendidos');
