<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aforo
    |--------------------------------------------------------------------------
    |
    | El cupo máximo diario todavía no lo define el área operativa (brief §12).
    | Mientras tanto se usa este valor al generar días nuevos.
    |
    */

    'aforo_cupo_maximo' => (int) env('AFORO_CUPO_MAXIMO', 2000),

    /*
    |--------------------------------------------------------------------------
    | Horario de operación
    |--------------------------------------------------------------------------
    |
    | Martes a domingo, 8:30 a 16:00. Lunes cerrado (brief §5.6).
    |
    */

    'horario' => [
        'apertura' => '08:30',
        'cierre'   => '16:00',
        'dia_cerrado' => Illuminate\Support\Carbon::MONDAY,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pasarela de pago
    |--------------------------------------------------------------------------
    |
    | El proveedor real está sin definir (brief §12) y la Fase 4 está bloqueada
    | hasta que haya convenio y credenciales. Todo el desarrollo corre contra
    | `simulada`, que reproduce el mismo contrato incluida la firma HMAC.
    |
    | El secreto del webhook cae en APP_KEY si no se define, para que el
    | entorno local funcione sin configuración extra. En producción DEBE
    | definirse PAGO_SECRETO_WEBHOOK con el valor que entregue el banco.
    |
    */

    'pago' => [
        'pasarela'        => env('PAGO_PASARELA', 'simulada'),
        'secreto_webhook' => env('PAGO_SECRETO_WEBHOOK', env('APP_KEY')),
    ],

];
