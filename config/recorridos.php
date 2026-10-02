<?php

/*
|--------------------------------------------------------------------------
| Recorridos del zoológico
|--------------------------------------------------------------------------
|
| Contenido de las seis paradas que se muestran en la portada y de la ficha
| que abre cada una. Vive aquí y no en la vista para que se pueda revisar de
| corrido, sin leer maquetado, y para que el día que el personal quiera
| editarlo sea mover esto a una tabla y nada más.
|
| PENDIENTE DE VALIDACIÓN POR SEMAHN
| ----------------------------------
| Los nombres de las paradas y las especies salen de fuentes de terceros
| (Wikipedia y prensa local), porque la página de atracciones del sitio
| oficial está caída. Antes de producción, el área del ZooMAT tiene que
| confirmar qué especies se exhiben hoy en cada recorrido: un zoológico
| mueve ejemplares, y prometer un quetzal que ya no está ahí es peor que
| no prometer nada.
|
| IMÁGENES
| --------
|   'arte'  → silueta que asoma por el costado de la tarjeta. Son SVG de
|             game-icons.net (CC BY 3.0), un solo trazo relleno, guardados
|             en public/images/recorridos/<slug>.svg. El crédito a los
|             autores va en el pie del portal, como pide la licencia.
|
|             Se quedan aunque lleguen las fotos: la silueta es el efecto
|             de la tarjeta y la foto es el contenido de la ficha. Son dos
|             registros distintos y mezclarlos en el mismo lugar se ve
|             revuelto.
|
|   'foto'  → foto de la especie DENTRO de la ficha. Sigue en null, a la
|             espera del material del ZooMAT. Recorte 4:3, mínimo 800x600,
|             WebP, en 'images/especies/<lo-que-sea>.webp'. El maquetado ya
|             reserva el espacio: meterlas es escribir la ruta y ninguna
|             vista se toca.
|
| El campo 'tinte' es la clase de fondo de la tarjeta. Tailwind lee este
| archivo gracias al @source que está declarado en resources/css/app.css:
| si agregas un tinte nuevo, tiene que ser uno de los ya definidos en el
| bloque de tokens o no se generará la clase.
|
*/

return [

    [
        'slug'     => 'vivario',
        'nombre'   => 'Vivario',
        'resumen'  => 'Serpientes, lagartijas y anfibios de la región, de cerca y en su ambiente.',
        'tinte'    => 'bg-tinte-menta',
        'arte'     => 'images/recorridos/vivario.svg',
        'especies' => [
            [
                'nombre'     => 'Nauyaca real',
                'cientifico' => 'Bothrops asper',
                'nota'       => 'La víbora más temida de la región y una de las que más se confunde con especies inofensivas.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Rana de ojos rojos',
                'cientifico' => 'Agalychnis callidryas',
                'nota'       => 'Nocturna. De día duerme pegada al envés de una hoja, con los ojos cerrados y el color escondido.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Boa',
                'cientifico' => 'Boa imperator',
                'nota'       => 'No es venenosa. Controla poblaciones de roedores y por eso es aliada de quien siembra.',
                'foto'       => null,
            ],
        ],
    ],

    [
        'slug'     => 'herpetario',
        'nombre'   => 'Herpetario',
        'resumen'  => 'Reptiles vivos y el porqué de su papel en el equilibrio del ecosistema.',
        'tinte'    => 'bg-tinte-cielo',
        'arte'     => 'images/recorridos/herpetario.svg',
        'especies' => [
            [
                'nombre'     => 'Iguana verde',
                'cientifico' => 'Iguana iguana',
                'nota'       => 'Herbívora y arborícola. Dispersa semillas por toda la selva con solo comer y moverse.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Garrobo',
                'cientifico' => 'Ctenosaura similis',
                'nota'       => 'Uno de los lagartos más veloces que se han medido en tierra.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Tortuga blanca',
                'cientifico' => 'Dermatemys mawii',
                'nota'       => 'De los ríos del sureste, en peligro crítico. Pasa casi toda su vida dentro del agua.',
                'foto'       => null,
            ],
        ],
    ],

    [
        'slug'     => 'museo-del-cocodrilo',
        'nombre'   => 'Museo del Cocodrilo',
        'resumen'  => 'El cocodrilo de pantano chiapaneco y su historia de casi cien millones de años.',
        'tinte'    => 'bg-tinte-crema',
        'arte'     => 'images/recorridos/museo-del-cocodrilo.svg',
        'especies' => [
            [
                'nombre'     => 'Cocodrilo de pantano',
                'cientifico' => 'Crocodylus moreletii',
                'nota'       => 'Especie mexicana. Estuvo al borde de la extinción por la caza y hoy se recupera.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Cocodrilo de río',
                'cientifico' => 'Crocodylus acutus',
                'nota'       => 'El de la costa. Tolera el agua salada y llega a superar los cuatro metros.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'La sala del museo',
                'cientifico' => null,
                'nota'       => 'Cráneos, piezas y la línea de tiempo de un animal que es anterior a las aves.',
                'foto'       => null,
            ],
        ],
    ],

    [
        'slug'     => 'museo-zoologico',
        'nombre'   => 'Museo Zoológico',
        'resumen'  => 'La colección del naturalista que fundó todo esto, y la fauna que documentó en Chiapas.',
        'tinte'    => 'bg-tinte-lavanda',
        'arte'     => 'images/recorridos/museo-zoologico.svg',
        'especies' => [
            [
                'nombre'     => 'La colección del fundador',
                'cientifico' => null,
                'nota'       => 'Miguel Álvarez del Toro dirigió el zoológico casi cincuenta años y describió especies nuevas para la ciencia.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Fauna documentada del estado',
                'cientifico' => null,
                'nota'       => 'El registro de lo que vive en Chiapas, levantado cuando no existían cámaras ni collares de rastreo.',
                'foto'       => null,
            ],
        ],
    ],

    [
        'slug'     => 'aviarios',
        'nombre'   => 'Aviarios y pajareras',
        'resumen'  => 'Tucanes, guacamayas, pericos y el quetzal, símbolo de las montañas de Chiapas.',
        'tinte'    => 'bg-tinte-durazno',
        'arte'     => 'images/recorridos/aviarios.svg',
        'especies' => [
            [
                'nombre'     => 'Quetzal',
                'cientifico' => 'Pharomachrus mocinno',
                'nota'       => 'Vive en el bosque de niebla. Su cola puede medir más que el resto del cuerpo.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Pavón',
                'cientifico' => 'Oreophasis derbianus',
                'nota'       => 'Solo existe en las montañas de Chiapas y Guatemala. Es el emblema del zoológico.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Guacamaya roja',
                'cientifico' => 'Ara macao',
                'nota'       => 'Forma pareja de por vida. En México quedan poblaciones libres solo en la selva del sureste.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Tucán real',
                'cientifico' => 'Ramphastos sulfuratus',
                'nota'       => 'El pico mide un tercio de su cuerpo y pesa muy poco: por dentro está hueco.',
                'foto'       => null,
            ],
        ],
    ],

    [
        'slug'     => 'senderos',
        'nombre'   => 'Senderos en selva',
        'resumen'  => 'Dos y medio kilómetros entre jabalíes, tepezcuintles, venados, coatíes y nutrias.',
        'tinte'    => 'bg-tinte-rosa',

        'arte'     => 'images/recorridos/senderos.svg',
        'especies' => [
            [
                'nombre'     => 'Tapir centroamericano',
                'cientifico' => 'Tapirus bairdii',
                'nota'       => 'El mamífero terrestre más grande del país y uno de los más amenazados.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Tepezcuintle',
                'cientifico' => 'Cuniculus paca',
                'nota'       => 'Roedor nocturno. Entierra semillas que olvida y termina sembrando árboles.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Coatí',
                'cientifico' => 'Nasua narica',
                'nota'       => 'Anda en grupos ruidosos de hembras y crías. Los machos adultos van solos.',
                'foto'       => null,
            ],
            [
                'nombre'     => 'Jabalí de collar',
                'cientifico' => 'Pecari tajacu',
                'nota'       => 'Se reconoce por la banda clara del cuello y por el olor con el que marca su territorio.',
                'foto'       => null,
            ],
        ],
    ],

];
