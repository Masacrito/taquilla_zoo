@extends('layouts.publico')

@section('titulo', 'Venta de boletos en línea')

{{--
    PORTADA DEL PORTAL

    ── Sistema visual ──────────────────────────────────────────────────
    Maquetada contra el DESIGN.md de Notion
    (.agents/skills/awesome-design-md/references/notion/DESIGN.md): una
    sola banda oscura de encabezado con la tarjeta de contenido
    sobresaliendo, superficies planas de 12px con filete de 1px, tintes
    pastel para agrupar, Inter con tracking negativo en los titulares y
    botones RECTANGULARES de 8px (nunca píldoras: es la regla que
    distingue a Notion de medio SaaS).

    Las dos desviaciones deliberadas (CTA en jade y banda verde selva en
    vez de marino) están razonadas en el bloque de tokens, al final de
    resources/css/app.css. El resto del portal y todo el panel interno
    siguen con el sistema institucional intacto.

    Lo que NO lleva, a propósito: puntos de colores regados, siluetas de
    hojas dibujadas a mano, grecas de relleno, parallax de capas. La
    atmósfera de selva la pone la fotografía y la ilustración, que son
    material del zoológico. Adorno dibujado encima de eso es lo que hace
    que una página se lea como plantilla.

    ── Imágenes ────────────────────────────────────────────────────────
    Las dos fotos de los jaguares son material oficial del ZooMAT,
    confirmado por el área que opera el sistema.

    hero-selva.jpg es la ilustración de difusión del zoológico. Se recorta
    por CSS para dejar fuera el "¡VISÍTANOS!" y el logo que trae quemados:
    ya hay un titular y ya hay un logo en el encabezado. PENDIENTE: no
    tiene versión .webp (la que existe con ese nombre es otra imagen,
    640x548), así que pesa 302 KB. Conviene generarla antes de producción.

    ── Datos ───────────────────────────────────────────────────────────
    Las cifras y los nombres de los hábitats vienen de fuentes de terceros
    (Wikipedia y prensa local) porque la página de atracciones del sitio
    oficial está caída. SEMAHN tiene que confirmarlas antes de salir a
    producción. Las tarifas y el estado del día salen de la base.
--}}

@section('ancho_completo')

    {{-- ── Banda del encabezado ────────────────────────────────────────
         Centrada, como el encabezado del sistema original. Cuatro
         elementos de texto y ni uno más: estado, titular, entrada y
         botones. El horario no va aquí abajo, ya lo dice el estado y lo
         repite la tarjeta que sigue. --}}
    <section class="bg-selva text-white" data-hero>
        <div class="mx-auto max-w-3xl px-4 pt-16 pb-40 text-center sm:pt-24 sm:pb-48">

            {{-- Estado calculado en vivo contra el calendario real. No es
                 una leyenda escrita a mano: si el personal cierra un día
                 por contingencia, aquí lo dice con su motivo. El punto de
                 color sí carga significado, por eso se queda. --}}
            <p class="inline-flex items-center gap-2 rounded-[6px] border border-white/15 bg-white/10
                      px-3 py-1.5 text-[13px] font-semibold text-white/90"
               data-revelar="abajo">
                <span class="h-2 w-2 rounded-full {{ $estado['abierto'] ? 'bg-jade' : 'bg-white/40' }}"
                      aria-hidden="true"></span>
                {{ $estado['titulo'] }}
                @if ($estado['detalle'])
                    <span class="font-normal text-white/60">· {{ $estado['detalle'] }}</span>
                @endif
            </p>

            {{-- Sin ancho máximo propio: el contenedor de 3xl ya lo parte en
                 dos renglones en escritorio, que es el tope. Un titular de
                 cuatro renglones siempre es un error de escala, no de
                 longitud del texto. --}}
            <h1 class="titular-hero mt-6 text-[34px] sm:text-[44px] lg:text-[56px]" data-revelar="abajo">
                Toda la fauna de Chiapas en un solo boleto
            </h1>

            <p class="mx-auto mt-6 max-w-lg text-lg leading-[1.5] text-white/70" data-revelar="abajo">
                1,400 animales de más de 170 especies, todas de la región, en 100 hectáreas de
                selva viva.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3" data-revelar="abajo">
                @auth('cliente')
                    <a href="{{ route('compras.crear') }}" class="btn-nota-primario">Comprar boletos</a>
                    <a href="{{ route('compras.index') }}" class="btn-nota-contorno">Mis compras</a>
                @else
                    <a href="{{ route('portal.registro') }}" class="btn-nota-primario">Comprar boletos</a>
                    <a href="{{ route('portal.ingresar') }}" class="btn-nota-contorno">Ya tengo cuenta</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- ── Tarjeta que sobresale ───────────────────────────────────────
         La pieza de firma del sistema: la tarjeta que rompe la banda y cae
         sobre el lienzo, con la única sombra profunda de toda la página.
         En el original lleva una captura del producto; aquí lleva a los
         animales, que es lo que la persona viene a ver, y debajo los dos
         datos que decide antes de comprar: cuándo abre y desde cuánto.

         El recorte: la ilustración es más ancha que el marco y se ancla a
         la izquierda, así quedan a la vista el tucán, la guacamaya y el
         tapir, y fuera el texto y el logo quemados del extremo derecho. --}}
    @php
        // La colección ya viene ordenada por precio, así que el primero que
        // no sea gratuito es el más barato que se cobra.
        $masBarato = $rubros->first(fn ($rubro) => ! $rubro->esGratis());
    @endphp

    <div class="relative z-10 mx-auto -mt-28 max-w-3xl px-4 sm:-mt-32" data-revelar="abajo">
        <figure class="overflow-hidden rounded-[12px] border border-filete bg-lienzo shadow-maqueta">
            <div class="relative h-52 overflow-hidden sm:h-72">
                <img src="{{ asset('images/hero-selva.jpg') }}"
                     alt="Ilustración de un tucán, una guacamaya y un tapir entre el follaje, tres de las especies que se exhiben en el ZooMAT"
                     fetchpriority="high" decoding="async"
                     class="absolute inset-y-0 left-0 h-full w-[170%] max-w-none object-cover object-left">
            </div>

            <figcaption class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1
                               border-t border-filete px-5 py-4 text-sm">
                <span class="font-medium text-tinta">Martes a domingo, 8:30 a 16:00 hrs</span>
                @if ($masBarato)
                    <span class="text-acero">
                        Entrada desde
                        <strong class="font-semibold tabular-nums text-tinta">{{ $masBarato->precioFormateado() }}</strong>
                    </span>
                @endif
            </figcaption>
        </figure>
    </div>

@endsection

@section('contenido')

    {{-- ── Cifras ──────────────────────────────────────────────────────
         Franja propia, a todo lo ancho y SIN TARJETA.

         Estuvieron un rato al lado de la ilustración y ahí se perdían: cinco
         animales a todo color contra cuatro números chicos, el ojo se va a la
         imagen y no regresa. Y la caja los encogía todavía más. Los datos
         duros se leen mejor sin contenedor, con tamaño y aire haciendo la
         jerarquía; el filete vertical basta para separarlos.

         Van en tinta, no en jade: el acento está reservado para el botón de
         comprar, que es la única acción de la página. --}}
    <section class="mt-12 sm:mt-16" data-revelar="abajo">
        <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4 sm:gap-x-0
                    sm:divide-x sm:divide-filete">
            @foreach ([
                ['1942',  'Fundado por Miguel Álvarez del Toro'],
                ['1,400', 'Animales bajo cuidado'],
                ['170+',  'Especies, todas de Chiapas'],
                ['100',   'Hectáreas en El Zapotal'],
            ] as [$cifra, $glosa])
                <div class="sm:px-6 sm:first:pl-0 sm:last:pr-0">
                    <p class="titular-nota text-[40px] leading-none tabular-nums text-tinta sm:text-[44px]">
                        {{ $cifra }}
                    </p>
                    <p class="mt-3 max-w-[22ch] text-[13px] leading-snug text-acero">{{ $glosa }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── Los dos jaguares ────────────────────────────────────────────
         El bloque que da personalidad al portal. No son adorno: son el
         mismo animal, y decirlo corrige una confusión que casi todo el
         mundo trae.

         `overflow-x-clip` no es decorativo: antes de revelarse, las dos
         tarjetas están desplazadas 2.5rem hacia los lados, y en pantallas
         angostas eso asomaba fuera del viewport y sacaba barra de scroll
         horizontal. `clip` en vez de `hidden` porque no crea contenedor de
         scroll. --}}
    <section class="mt-24 overflow-x-clip">
        <div class="max-w-2xl" data-revelar="abajo">
            <h2 class="titular-nota text-[28px] leading-[1.2] text-tinta sm:text-[36px]">
                Son el mismo animal
            </h2>
            <p class="mt-4 text-base leading-[1.55] text-pizarra">
                La «pantera negra» de América no es otra especie: es un jaguar melánico, con las
                mismas manchas escondidas bajo el pelaje oscuro. Si te acercas con la luz correcta,
                se alcanzan a ver.
            </p>
        </div>

        <div class="mt-8 grid gap-5 sm:grid-cols-2">
            @foreach ([
                ['archivo' => 'jaguar-moteado', 'lado' => 'izquierda', 'encuadre' => '50% 42%',
                 'nombre'  => 'Jaguar', 'pie' => 'Panthera onca'],
                ['archivo' => 'jaguar-negro',   'lado' => 'derecha',   'encuadre' => '50% 45%',
                 'nombre'  => 'Jaguar melánico', 'pie' => 'La misma especie, con melanismo'],
            ] as $gato)
                <figure class="overflow-hidden rounded-[12px] border border-filete bg-lienzo"
                        data-revelar="{{ $gato['lado'] }}">
                    <picture>
                        <source srcset="{{ asset("images/{$gato['archivo']}.webp") }}" type="image/webp">
                        <img src="{{ asset("images/{$gato['archivo']}.jpg") }}"
                             alt="{{ $gato['nombre'] }} en el ZooMAT"
                             loading="lazy" decoding="async"
                             class="h-72 w-full object-cover sm:h-80"
                             style="object-position: {{ $gato['encuadre'] }}">
                    </picture>
                    <figcaption class="border-t border-filete px-5 py-4">
                        <p class="text-[15px] font-medium text-tinta">{{ $gato['nombre'] }}</p>
                        <p class="mt-0.5 text-[13px] text-acero">{{ $gato['pie'] }}</p>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    {{-- ── Tarifas ─────────────────────────────────────────────────────
         Una sola superficie con las tarifas en renglones, no una tarjeta
         por tarifa: son tres, y tres tarjetas idénticas en fila es el
         relleno más reconocible que existe. El filete solo separa
         renglones; el precio manda por tamaño y por alineación, no por
         color (el acento se reserva para el botón). --}}
    <section class="mt-24" data-revelar="abajo">
        <div class="max-w-xl">
            <h2 class="titular-nota text-[28px] leading-[1.2] text-tinta sm:text-[36px]">
                Tarifas vigentes
            </h2>
            <p class="mt-4 text-base leading-[1.55] text-pizarra">
                Compra en línea, recibe tu código QR por correo y preséntalo en el acceso. Sin filas.
            </p>
        </div>

        <div class="mt-8 overflow-hidden rounded-[12px] border border-filete bg-lienzo">
            @if ($rubros->isNotEmpty())
                <ul class="divide-y divide-filete-suave">
                    @foreach ($rubros as $rubro)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-8 gap-y-1 px-6 py-5">
                            <div class="min-w-0">
                                <p class="text-[15px] font-medium text-tinta">{{ $rubro->tipo }}</p>
                                @if ($rubro->descripcion)
                                    <p class="mt-1 text-sm leading-[1.5] text-acero">{{ $rubro->descripcion }}</p>
                                @endif
                            </div>

                            @if ($rubro->esGratis())
                                {{-- Magenta y no jade: el manual reserva el magenta para los
                                     estados especiales, y sobre el rosa suave da 4.6:1 de
                                     contraste. El mismo par jade sobre menta se queda en
                                     4.45:1, debajo del mínimo AA para 13px. --}}
                                <span class="chip-nota shrink-0 bg-magenta-suave text-magenta">Entrada gratuita</span>
                            @else
                                <p class="titular-nota shrink-0 text-[22px] tabular-nums text-tinta">
                                    {{ $rubro->precioFormateado() }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                {{-- Requisitos que se validan en el acceso. Están tomados del
                     mismo texto que ya publica el pie del portal: si alguien
                     elige mal el tipo de visitante, ahí se paga boleto. --}}
                <p class="border-t border-filete bg-crema-suave px-6 py-4 text-sm leading-[1.5] text-acero">
                    Tercera edad presenta INAPAM. Estudiante presenta credencial. Niño Pavón gratis
                    hasta 1.20 m de estatura.
                </p>
            @else
                <p class="px-6 py-8 text-center text-sm text-acero">
                    Todavía no hay tarifas publicadas. Vuelve pronto.
                </p>
            @endif
        </div>
    </section>

    {{-- ── Recorridos ──────────────────────────────────────────────────
         Seis paradas, cada una con su tinte pastel: es el bloque que pone
         color a la página sin una sola decoración dibujada.

         Cada tarjeta es un ANCLA de verdad hacia su ficha, no un div con un
         clic encima. Así funciona con teclado, con lector de pantalla y
         hasta sin JavaScript, y de paso se puede compartir el enlace
         directo a un recorrido.

         El arte del animal se asoma al pasar el mouse SOLO en escritorio;
         en celular se ve fijo, porque en una pantalla táctil el hover no
         existe y el público compra desde el celular. La información de
         verdad nunca depende de ese efecto: vive en la ficha.

         El contenido sale de config/recorridos.php. Las imágenes están
         pendientes del material del ZooMAT; el espacio ya está reservado. --}}
    <section class="mt-24" data-revelar="abajo">
        <div class="max-w-xl">
            <h2 class="titular-nota text-[28px] leading-[1.2] text-tinta sm:text-[36px]">
                Qué vas a ver
            </h2>
            <p class="mt-4 text-base leading-[1.55] text-pizarra">
                Seis recorridos dentro del zoológico, más de dos kilómetros de senderos en selva.
                Toca uno para ver qué especies te vas a encontrar.
            </p>
        </div>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (config('recorridos') as $recorrido)
                <a href="#ficha-{{ $recorrido['slug'] }}"
                   data-ficha="ficha-{{ $recorrido['slug'] }}"
                   class="tarjeta-recorrido group {{ $recorrido['tinte'] }} rounded-[12px] p-8
                          focus:outline-none focus-visible:ring-3 focus-visible:ring-jade/30">

                    {{-- `relative` para que el texto quede por encima del arte,
                         que va pegado a la esquina inferior derecha. --}}
                    <span class="relative block text-[22px] font-semibold leading-[1.3] text-carbon">
                        {{ $recorrido['nombre'] }}
                    </span>
                    <span class="relative mt-3 block max-w-[26ch] text-sm leading-[1.5] text-carbon/70">
                        {{ $recorrido['resumen'] }}
                    </span>
                    <span class="relative mt-5 inline-flex items-center gap-1.5 text-[13px] font-semibold text-carbon">
                        Ver especies
                        <span class="transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                    </span>

                    @if ($recorrido['arte'])
                        <img src="{{ asset($recorrido['arte']) }}" alt="" loading="lazy" decoding="async"
                             class="arte-recorrido">
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    {{-- ── Fichas de cada recorrido ────────────────────────────────────
         <dialog> nativo: la trampa de foco, el cierre con Esc y el fondo
         oscurecido vienen del navegador, sin una sola librería. El script
         del final las conecta; si no hay JavaScript, el ancla de la tarjeta
         las muestra aquí mismo como un bloque normal.

         Los marcos vacíos de 4:3 son los huecos de las fotos de cada
         especie. Reservan el espacio desde ahora para que meter el material
         no mueva el maquetado ni un pixel. --}}
    @foreach (config('recorridos') as $recorrido)
        <dialog id="ficha-{{ $recorrido['slug'] }}" class="ficha"
                aria-labelledby="ficha-{{ $recorrido['slug'] }}-titulo">
            <article class="p-6 sm:p-8">

                <header class="flex items-start justify-between gap-6">
                    <div>
                        <h2 id="ficha-{{ $recorrido['slug'] }}-titulo"
                            class="titular-nota text-[24px] leading-[1.2] text-tinta">
                            {{ $recorrido['nombre'] }}
                        </h2>
                        <p class="mt-2 max-w-md text-sm leading-[1.5] text-pizarra">
                            {{ $recorrido['resumen'] }}
                        </p>
                    </div>

                    {{-- Con texto y no solo con una equis: un botón de icono
                         sin etiqueta no se anuncia en un lector de pantalla.
                         Los 44px de alto son el mínimo táctil. --}}
                    <button type="button" data-cerrar
                            class="btn-nota-secundario min-h-[44px] shrink-0">Cerrar</button>
                </header>

                <ul class="mt-8 grid gap-6 sm:grid-cols-2">
                    @foreach ($recorrido['especies'] as $especie)
                        <li>
                            @if ($especie['foto'])
                                <img src="{{ asset($especie['foto']) }}"
                                     alt="{{ $especie['nombre'] }} en el ZooMAT"
                                     loading="lazy" decoding="async"
                                     class="aspect-[4/3] w-full rounded-[8px] border border-filete object-cover">
                            @else
                                <div class="aspect-[4/3] w-full rounded-[8px] border border-filete bg-crema"
                                     aria-hidden="true"></div>
                            @endif

                            <p class="mt-3 text-[15px] font-medium text-tinta">{{ $especie['nombre'] }}</p>
                            @if ($especie['cientifico'])
                                <p class="text-[13px] italic text-acero">{{ $especie['cientifico'] }}</p>
                            @endif
                            <p class="mt-1.5 text-sm leading-[1.5] text-pizarra">{{ $especie['nota'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </article>
        </dialog>
    @endforeach

    {{-- ── Grupos escolares ────────────────────────────────────────────
         La tarjeta de amarillo fuerte, que el sistema reserva para el
         bloque de mayor énfasis de la página. Es una de las razones por
         las que la gente busca al ZooMAT, así que no puede vivir solo en
         el pie. Dato del sitio oficial. --}}
    <section class="mt-24" data-revelar="abajo">
        <div class="rounded-[12px] bg-tinte-amarillo-fuerte p-8 sm:p-12">
            <h2 class="titular-nota max-w-xl text-[28px] leading-[1.2] text-carbon">
                ¿Vienes con un grupo escolar?
            </h2>
            <p class="mt-4 max-w-2xl text-base leading-[1.55] text-carbon/80">
                Educación Ambiental atiende visitas guiadas de preescolar a universidad, de martes a
                viernes de 9:30 a 14:30 hrs. El programa dura 40 minutos e incluye charlas, juegos
                didácticos, cuentos y teatro guiñol; después el grupo recorre el parque por su cuenta
                o con guía. Se solicita con oficio a
                <a href="mailto:atencionescolarzoomat@gmail.com"
                   class="font-medium text-carbon underline decoration-carbon/30 underline-offset-2
                          hover:decoration-carbon">atencionescolarzoomat@gmail.com</a>.
            </p>
        </div>
    </section>

    {{-- ── Cierre ──────────────────────────────────────────────────────
         Franja de superficie con mucho aire y una sola acción, con la
         misma etiqueta que el encabezado y que la barra inferior: un
         propósito, un texto. --}}
    <section class="mt-6" data-revelar="abajo">
        <div class="rounded-[12px] border border-filete bg-crema p-8 text-center sm:p-16">
            <h2 class="titular-nota text-[28px] leading-[1.2] text-tinta sm:text-[36px]">
                Tu boleto, en tu celular
            </h2>
            <p class="mx-auto mt-4 max-w-md text-base leading-[1.55] text-pizarra">
                Eliges el día en el calendario, pagas en línea y te llega el código QR por correo.
                Lo muestras en el acceso y entras.
            </p>
            <div class="mt-8">
                @auth('cliente')
                    <a href="{{ route('compras.crear') }}" class="btn-nota-primario">Comprar boletos</a>
                @else
                    <a href="{{ route('portal.registro') }}" class="btn-nota-primario">Comprar boletos</a>
                @endauth
            </div>
        </div>
    </section>

    <script>
        /*
         * Fichas de los recorridos.
         *
         * Las tarjetas ya son anclas que funcionan solas; esto nada más las
         * convierte en ventana modal cuando el navegador puede con ella.
         *
         * `data-fichas` en el documento es la señal para el CSS: mientras no
         * exista, la ficha apuntada por el fragmento se muestra como bloque
         * normal. Así, un navegador sin <dialog> o sin JavaScript sigue
         * llevando a la persona a la información en vez de dejarla con un
         * enlace muerto.
         */
        (function () {
            const soportaModal = typeof HTMLDialogElement === 'function'
                && typeof HTMLDialogElement.prototype.showModal === 'function';

            if (!soportaModal) return;

            const fichas = document.querySelectorAll('dialog.ficha');
            if (!fichas.length) return;

            document.documentElement.setAttribute('data-fichas', '');

            const abrir = (ficha) => {
                if (!ficha || ficha.open) return;
                ficha.showModal();
            };

            /*
             * El modal deja inerte lo de atrás, pero NO impide que la página
             * siga desplazándose detrás. En celular eso se siente roto, así
             * que se congela mientras haya una ficha abierta.
             *
             * Se vigila el ATRIBUTO `open` y no el evento `close`: hay
             * navegadores que no mandan ese evento (se comprobó uno durante
             * el desarrollo), y si no llega, la página se queda trabada sin
             * poder desplazarse. El atributo, en cambio, cambia siempre: al
             * abrir, al cerrar con el botón, con un clic afuera y con Esc.
             */
            const vigilante = new MutationObserver(() => {
                const hayAbierta = [...fichas].some((ficha) => ficha.open);
                document.body.style.overflow = hayAbierta ? 'hidden' : '';
            });

            fichas.forEach((ficha) => {
                vigilante.observe(ficha, { attributes: true, attributeFilter: ['open'] });
            });

            document.querySelectorAll('[data-ficha]').forEach((enlace) => {
                enlace.addEventListener('click', (evento) => {
                    const ficha = document.getElementById(enlace.dataset.ficha);
                    if (!ficha) return;   // sin ficha, el ancla se queda como estaba
                    evento.preventDefault();
                    abrir(ficha);
                });
            });

            fichas.forEach((ficha) => {
                // Clic fuera del contenido: el <dialog> es el blanco cuando se
                // toca el área oscurecida, porque su relleno es cero y el
                // contenido va dentro de un <article>.
                ficha.addEventListener('click', (evento) => {
                    if (evento.target === ficha) ficha.close();
                });

                ficha.querySelector('[data-cerrar]')?.addEventListener('click', () => ficha.close());
            });

            // Enlace directo: zoomat.chiapas.gob.mx/#ficha-aviarios abre esa
            // ficha de una vez. Sirve para compartir por WhatsApp un recorrido
            // en concreto.
            if (window.location.hash.length > 1) {
                // Por id y no armando un selector con el fragmento: un hash
                // cualquiera («#1234») produce un selector inválido y tira
                // una excepción.
                const objetivo = document.getElementById(window.location.hash.slice(1));
                if (objetivo && objetivo.matches('dialog.ficha')) abrir(objetivo);
            }
        })();

        /*
         * Barra de compra pegada al borde inferior.
         *
         * Aparece cuando el encabezado sale de vista, así el botón de comprar
         * queda al alcance mientras la persona lee el resto. Es lo único de
         * toda la portada que de verdad mueve la aguja de vender un boleto.
         */
        (function () {
            // Se espera al DOM completo A PROPÓSITO: este script vive dentro
            // del contenido, y la barra la pinta el layout DESPUÉS. Sin la
            // espera, querySelector devuelve null y el observador nunca se
            // crea y la barra se queda escondida para siempre.
            const montar = () => {
                const barra = document.querySelector('[data-barra-compra]');
                const hero  = document.querySelector('[data-hero]');
                if (!barra || !hero || !('IntersectionObserver' in window)) return;

                const observador = new IntersectionObserver(([entrada]) => {
                    barra.toggleAttribute('data-visible', !entrada.isIntersecting);
                }, { threshold: 0 });

                observador.observe(hero);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', montar);
            } else {
                montar();
            }
        })();

        /*
         * Aparición al hacer scroll.
         *
         * IntersectionObserver y NO un listener de scroll: escuchar el scroll
         * dispara decenas de veces por segundo y es de las causas más comunes
         * de tirones en Android de gama media, que es justo el público. Cada
         * bloque se observa una sola vez: una vez que apareció, se deja de
         * vigilar y no vuelve a esconderse al subir.
         */
        (function () {
            const bloques = document.querySelectorAll('[data-revelar]');
            if (!bloques.length) return;

            // Sin soporte, todo visible de una vez: más vale sin animación
            // que sin contenido.
            if (!('IntersectionObserver' in window)) {
                bloques.forEach((b) => b.setAttribute('data-visible', ''));
                return;
            }

            const observador = new IntersectionObserver((entradas, obs) => {
                entradas.forEach((entrada) => {
                    if (!entrada.isIntersecting) return;
                    entrada.target.setAttribute('data-visible', '');
                    obs.unobserve(entrada.target);
                });
            }, {
                // Se dispara un poco antes de que el bloque toque el borde
                // inferior: al llegar con la vista ya terminó de entrar.
                rootMargin: '0px 0px -12% 0px',
                threshold: 0.1,
            });

            bloques.forEach((b) => observador.observe(b));
        })();
    </script>
@endsection
