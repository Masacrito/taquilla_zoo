<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registro de fallos
    |--------------------------------------------------------------------------
    |
    | `codigo_minimo` decide qué se guarda. En 500 entran los fallos del
    | servidor y las excepciones no controladas, que es lo que de verdad está
    | roto. Bajarlo a 400 traería también los 404, que en su mayoría son bots
    | buscando /wp-admin y solo harían ruido.
    |
    | El aviso por correo va al Super Admin, con freno de 30 minutos por tipo
    | de fallo para que una excepción en bucle no inunde el buzón.
    |
    */

    'errores' => [
        'registrar'          => (bool) env('ERRORES_REGISTRAR', true),
        'codigo_minimo'      => (int) env('ERRORES_CODIGO_MINIMO', 500),
        'avisar_por_correo'  => (bool) env('ERRORES_AVISAR_CORREO', true),

        // Días que se conservan los fallos ya atendidos antes de purgarlos.
        'dias_retencion'     => (int) env('ERRORES_DIAS_RETENCION', 90),
    ],

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

        /*
         | Entornos donde la pasarela simulada puede operar. Es la ÚNICA
         | respuesta a «dónde se permite cobrar de mentiras»: la consultan la
         | fábrica de pasarelas y la pantalla que sustituye a la del banco.
         |
         | Para montar un entorno de pruebas parecido a producción, agrega
         | aquí su nombre (por ejemplo 'staging') y despliega con ese APP_ENV.
         */
        'entornos_simulada' => ['local', 'testing', 'staging'],
    ],

];
