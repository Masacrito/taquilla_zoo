{{-- ════════════════════════════════════════════════════════════════
     Follaje — silueta estilizada para bordes del hero y marcos.

     Es UNA sola forma de hoja repetida con rotaciones distintas. Una
     hoja simple se lee bien a cualquier tamaño; repetirla da la
     frondosidad sin el riesgo de que un trazo complicado salga raro
     al escalar.

     En SVG y no en foto por tres razones: no arrastra licencias,
     pesa unos pocos KB contra los cientos de una imagen, y una
     silueta estilizada no afirma ser un paisaje concreto. El Zapotal
     es selva baja caducifolia, no la selva húmeda que la gente
     imagina, y una foto equivocada en el sitio de la Secretaría de
     Medio Ambiente sí se nota. Los verdes salen de la portada de
     redes del ZooMAT, para que el portal y sus publicaciones hablen
     del mismo color aunque el registro sea distinto.

     ────────────────────────────────────────────────────────────────
     PARÁMETROS

       $lado    'izquierda' | 'derecha'   de qué borde crece
       $capa    1 (lejos) | 2 (medio) | 3 (cerca)
       $modo    'contraluz' (sobre fondo plano) | 'silueta' (sobre foto)
       $sufijo  string único por instancia. OBLIGATORIO si incluyes
                el mismo lado+capa dos veces en la misma página (por
                ejemplo hero + marco de hábitats). Sin él, los
                <use href="#hoja-…"> de la segunda instancia apuntan
                al <defs> de la primera: el navegador se queda con el
                primer id que encuentra y el resto es HTML inválido.

     ────────────────────────────────────────────────────────────────
     USO

       @include('publico.parciales.follaje', [
         'lado'   => 'izquierda',
         'capa'   => 1,
         'sufijo' => 'hero',
       ])
     ════════════════════════════════════════════════════════════════ --}}
@php
    $lado   ??= 'izquierda';
    $capa   ??= 2;
    $sufijo ??= 'x';

    // Cada capa más cercana: más grande, más opaca y MÁS CLARA.
    //
    // Lo de más clara no es capricho. El encabezado tiene fondo oscuro,
    // así que un verde más oscuro que el fondo simplemente desaparece:
    // probado, el plano cercano quedaba en rgb(20,50,49) mezclado
    // contra un fondo rgb(31,41,51) y no se veía nada. Sobre oscuro, el
    // follaje se lee como hoja a contraluz, no como silueta.
    // $modo decide si el follaje se lee como hoja a contraluz o como
    // sombra recortada, y eso depende ENTERAMENTE de lo que tenga detrás:
    //
    //   'contraluz'  sobre fondo plano oscuro. El verde va más claro que el
    //                fondo; uno más oscuro simplemente desaparece.
    //   'silueta'    sobre fotografía. Aquí se invierte: la hoja va casi
    //                negra y el ojo la lee como sombra en primer plano. Es
    //                además mucho más indulgente con la forma — una silueta
    //                no necesita detalle para leerse como hoja.
    $modo ??= 'contraluz';

    [$escala, $opacidad, $color] = $modo === 'silueta'
        ? match ((int) $capa) {
            1       => [0.60, '0.45', '#0d2b21'],
            3       => [1.20, '0.85', '#061712'],
            default => [0.90, '0.65', '#0a2119'],
        }
        : match ((int) $capa) {
            1       => [0.55, '0.28', '#2f7a5e'],
            3       => [1.15, '0.50', '#4fb98a'],
            default => [0.85, '0.38', '#3d9b74'],
        };

    // El viewBox es ALTO Y ANGOSTO a propósito, del mismo formato que
    // el contenedor. Con uno cuadrado y slice, el SVG escalaba para
    // cubrir y recortaba justo los bordes laterales, que es donde
    // nacen las frondas.
    $anchoVb = 140;

    // `translate` ANTES de `scale(-1,1)`. El resultado es el mismo que
    // al revés, pero el orden importa en cuanto se añada otra
    // transformación a la cadena: el segundo transform opera sobre el
    // sistema de coordenadas que dejó el primero. Además el
    // desplazamiento queda escrito como el ancho real del viewBox y no
    // como un número mágico atado a él.
    $volteo = $lado === 'derecha'
        ? "translate({$anchoVb},0) scale(-1,1)"
        : '';

    // Anclaje del recorte al borde del que NACE la fronda.
    //
    // Con `xMidYMid slice` el SVG escalaba para cubrir y recortaba
    // simétricamente por los dos lados: se comía el borde donde arranca
    // el tallo y dejaba visible solo el centro, que es donde hay menos
    // silueta. `xMinYMid` pega el recorte al borde izquierdo;
    // `xMaxYMid`, al derecho. Como el grupo del lado derecho ya está
    // espejado, el ancla también se invierte para que ambos lados se
    // lean igual.
    $anclaje = $lado === 'derecha' ? 'xMaxYMid slice' : 'xMinYMid slice';

    // id único por instancia, no por combinación lado+capa.
    //
    // `'hoja-' . $lado . '-' . $capa` producía el mismo id en el hero y
    // en el marco de hábitats, y el navegador resolvía TODOS los <use>
    // contra el primer <defs> de la página: el follaje de hábitats se
    // pintaba con el color y la opacidad del hero.
    //
    // El sufijo tiene que venir del padre: @include no comparte estado
    // entre llamadas —no estamos dentro de una función—, así que un
    // contador estático aquí dentro no serviría.
    $id = 'hoja-' . $lado . '-' . $capa . '-' . preg_replace('/[^a-z0-9]+/i', '-', $sufijo);

    // Las frondas nacen dentro del viewBox y no en negativo. Dibujadas
    // desde x negativo, el recorte dejaba fuera la parte ancha de cada
    // hoja y solo asomaba la cuña del arranque: se leían como banderines
    // triangulares, no como follaje.
    $origenX = 6;
@endphp

<svg viewBox="0 0 {{ $anchoVb }} 420"
     class="h-full w-full" fill="none"
     preserveAspectRatio="{{ $anclaje }}"
     aria-hidden="true" focusable="false">

    <defs>
        {{-- Una hoja: dos curvas que se encuentran en punta. El nervio
             central le da lectura sin necesidad de detalle. --}}
        <g id="{{ $id }}">
            <path d="M0 0 Q34 -17 76 0 Q34 17 0 0Z" fill="{{ $color }}"></path>
            <path d="M4 0 H68" stroke="{{ $color }}" stroke-width="1.4"
                  opacity="0.5" stroke-linecap="round"></path>
        </g>
    </defs>

    {{-- La fronda nace de una esquina y se abre en abanico. El grupo
         entero se voltea para el lado derecho, así una sola definición
         sirve para los dos bordes. --}}
    <g transform="{{ $volteo }}" opacity="{{ $opacidad }}">

        <g transform="translate({{ $origenX }}, 26) scale({{ $escala }})">
            <use href="#{{ $id }}" transform="rotate(-8)"></use>
            <use href="#{{ $id }}" transform="translate(6, 34) rotate(16)"></use>
            <use href="#{{ $id }}" transform="translate(2, 74) rotate(38)"></use>
            <use href="#{{ $id }}" transform="translate(-8, 112) rotate(58)"></use>
            {{-- Tallo: une el abanico y evita que las hojas floten sueltas. --}}
            <path d="M-6 -4 Q24 60 6 140" stroke="{{ $color }}" stroke-width="3.5"
                  fill="none" stroke-linecap="round"></path>
        </g>

        <g transform="translate({{ $origenX - 4 }}, 168) scale({{ $escala * 1.15 }})">
            <use href="#{{ $id }}" transform="rotate(-22)"></use>
            <use href="#{{ $id }}" transform="translate(10, 38) rotate(6)"></use>
            <use href="#{{ $id }}" transform="translate(4, 80) rotate(30)"></use>
            <path d="M-4 -6 Q30 50 10 96" stroke="{{ $color }}" stroke-width="4"
                  fill="none" stroke-linecap="round"></path>
        </g>

        <g transform="translate({{ $origenX }}, 298) scale({{ $escala * 0.9 }})">
            <use href="#{{ $id }}" transform="rotate(-40)"></use>
            <use href="#{{ $id }}" transform="translate(14, 30) rotate(-8)"></use>
            <use href="#{{ $id }}" transform="translate(6, 68) rotate(22)"></use>
            <path d="M-2 -8 Q26 40 12 88" stroke="{{ $color }}" stroke-width="3.5"
                  fill="none" stroke-linecap="round"></path>
        </g>
    </g>
</svg>
