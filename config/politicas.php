<?php

/*
|--------------------------------------------------------------------------
| Políticas de entrega, cancelación y reembolso
|--------------------------------------------------------------------------
|
| El texto que se publica en /politicas. El banco lo exige para dar de alta
| el cobro en línea (guía KYC e-commerce, pregunta 7: sin política publicada
| y de acceso público no hay alta), y la pregunta 10 pide que devoluciones y
| contracargos no pasen del 1 %; por eso el texto es deliberadamente acotado.
|
| ESTE CONTENIDO ES UN BORRADOR. Lo redactó desarrollo a partir de lo que el
| sistema hace hoy y de lo ya confirmado por el área operativa (grupo
| incompleto: sin devolución). Los plazos y los supuestos de reembolso están
| pendientes de que los valide el área y, en su caso, Jurídico. Mientras
| `en_revision` sea true, la página lo dice.
|
| Vive aquí y no en la vista para que ajustar la política sea cambiar texto
| en un solo archivo, sin tocar HTML.
|
*/

return [

    // Ponlo en false cuando el área haya aprobado el texto.
    'en_revision' => true,

    'actualizado' => '2026-10-02',

    // A dónde escribe quien pide un reembolso o una aclaración.
    'correo_contacto' => env('POLITICAS_CORREO_CONTACTO', 'zoomat@zoomat.chiapas.gob.mx'),

    'secciones' => [

        [
            'titulo'   => 'Entrega de los boletos',
            'parrafos' => [
                'Los boletos son digitales. En cuanto el banco confirma el pago, enviamos al correo electrónico indicado en la compra un comprobante con el código QR de acceso, en el mensaje y en un PDF adjunto. La entrega es inmediata; no hay envío físico.',
                'Si el correo no llega en unos minutos, revisa la carpeta de spam o correo no deseado. Quien compró con cuenta también encuentra su código en la sección «Mis compras».',
            ],
        ],

        [
            'titulo'   => 'Vigencia y uso',
            'parrafos' => [
                'Cada boleto es válido únicamente para la fecha de visita elegida al comprar, dentro del horario de operación: martes a domingo, de 8:30 a 16:00 horas. Los lunes el zoológico no abre.',
                'El código QR ampara el número de pases comprados. Si el grupo llega por partes, los pases se descuentan conforme entran.',
                'El tipo de visitante se valida en el acceso: tercera edad presenta credencial INAPAM. Si el boleto no corresponde, la diferencia se paga en taquilla.',
            ],
        ],

        [
            'titulo'   => 'Cambios de fecha',
            'parrafos' => [
                'Por el momento los boletos no admiten cambio de fecha. Verifica el día de tu visita antes de pagar.',
            ],
        ],

        [
            'titulo'   => 'Cancelaciones y reembolsos',
            'parrafos' => [
                'Por tratarse de un boleto con fecha específica, una vez emitido no es reembolsable por causas atribuibles al visitante: inasistencia, llegada fuera del horario, grupo incompleto o haber elegido un tipo de visitante que no corresponde.',
                'Sí procede el reembolso total cuando el zoológico cierra el día de la visita por causas propias o de fuerza mayor, y cuando hubo un cobro duplicado o un error del sistema.',
            ],
            'lista' => [
                'Se solicita por correo electrónico, indicando el folio de la compra, dentro de los 5 días hábiles siguientes a la fecha de visita o al cobro.',
                'El zoológico responde la solicitud en un máximo de 5 días hábiles.',
                'El reembolso se hace al mismo medio de pago utilizado; el tiempo en que se refleja depende del banco emisor.',
                'Al autorizarse el reembolso, la compra se cancela y su código QR deja de ser válido.',
            ],
        ],

        [
            'titulo'   => 'Aclaraciones',
            'parrafos' => [
                'Para cualquier duda sobre una compra o un cargo no reconocido, escríbenos con el folio de la compra. Conserva tu comprobante: es el respaldo de la operación.',
            ],
        ],
    ],
];
