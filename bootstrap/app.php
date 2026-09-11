<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Webhooks de pago: sin sesión y sin CSRF, autenticados por firma
            // HMAC (brief §7). Van fuera del grupo `web` a propósito.
            Route::middleware('api')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol'             => \App\Http\Middleware\EnsureRole::class,
            'permission'      => \App\Http\Middleware\EnsurePermission::class,
            'account.status'  => \App\Http\Middleware\CheckAccountStatus::class,
            'guard.exclusivo' => \App\Http\Middleware\EnsureGuardExclusivo::class,
        ]);

        // `account.status` NO va en el grupo `web` global: eso lo aplicaría
        // también al portal público del visitante, que el brief §3.2 excluye
        // expresamente. Se aplica por ruta, sobre los grupos internos.

        // El aislamiento de guards tiene que evaluarse ANTES que `auth`.
        // Laravel reordena los middleware según su lista de prioridad, así que
        // el orden declarado en la ruta NO basta: `auth` se adelantaría y una
        // cuenta interna que toca el portal recibiría un redirect al login en
        // vez del 403 que exige el brief §10.6.
        //
        // La referencia es el CONTRATO AuthenticatesRequests, no la clase
        // Authenticate: es lo que aparece en la lista de prioridad. Pasar la
        // clase concreta no falla, pero deja este middleware al final de la
        // lista, que es exactamente lo contrario de lo que se busca.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\EnsureGuardExclusivo::class,
        );

        // El banco no manda token CSRF. La autenticidad del webhook la da la
        // firma HMAC que verifica la pasarela, no la sesión.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Deja constancia del fallo en la tabla `errores` y avisa al Super
        // Admin. Va en tabla aparte de `movimientos`, que es la bitácora de
        // auditoría: un fallo no es la operación de nadie, y el volumen de
        // los bots sepultaría las entradas que sí tienen valor.
        //
        // `report` no reemplaza el log de Laravel: se ejecuta ADEMÁS. Si la
        // base está caída —el peor momento posible— el registro falla pero
        // el log de archivo sigue recibiendo el fallo.
        $exceptions->report(function (\Throwable $e) {
            app(\App\Services\Auditoria\RegistroErroresService::class)
                ->registrar($e, request());
        });
    })->create();
