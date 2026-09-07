@extends('layouts.publico')

@section('titulo', 'Venta de boletos en línea')

{{--
    PORTADA DEL PORTAL

    Las cifras (año, número de animales, especies, hectáreas) y los nombres de
    los hábitats vienen de fuentes de terceros —Wikipedia y prensa local—
    porque la página de atracciones del sitio oficial está caída. SEMAHN tiene
    que confirmarlas antes de salir a producción.

    Las grecas de los hábitats son provisionales: están hechas para sustituirse
    por fotografía oficial del ZooMAT sin tocar el maquetado. Cada tarjeta lleva
    la instrucción de cómo cambiarla.

    PENDIENTE ANTES DE PRODUCCIÓN — las dos fotos de los jaguares llegaron con
    nombre de archivo del CDN de Facebook. Si son de la página oficial del
    ZooMAT no hay problema, pero eso tiene que confirmarlo SEMAHN por escrito.
    Si resultan de un tercero, hay que sustituirlas: en Wikimedia Commons hay
    jaguares con licencia libre verificable, o usar fotografía propia. Un sitio
    de gobierno con una imagen sin permiso es un problema que aparece después.
--}}

@section('ancho_completo')

    {{-- ── Encabezado ──────────────────────────────────────────────────
         Fondo oscuro por dos razones: sostiene la foto del jaguar negro que
         viene abajo, y deja respirar al jade, que en fondo claro compite con
         el texto. --}}
    <section class="relative overflow-hidden bg-texto text-white">

        {{-- Halo de jade. Puramente atmosférico, va detrás del contenido. --}}
        <div class="pointer-events-none absolute inset-0 opacity-40"
             style="background: radial-gradient(60rem 30rem at 70% -10%, #009887 0%, transparent 60%);"
             aria-hidden="true"></div>

        <div class="relative mx-auto max-w-5xl px-4 py-20 sm:py-28">
            <p class="titulo text-[11px] text-jade" data-revelar="abajo">
                Tuxtla Gutiérrez · Chiapas
            </p>

            <h1 class="titulo mt-4 text-3xl leading-[1.15] sm:text-5xl" data-revelar="abajo">
                Zoológico Regional<br>Miguel Álvarez del Toro
            </h1>

            <p class="mt-6 max-w-xl text-base leading-relaxed text-white/70" data-revelar="abajo">
                El único zoológico que exhibe <strong class="font-semibold text-white">solo fauna
                de Chiapas</strong>. Más de 1,400 animales en 100 hectáreas de selva viva, dentro
                de la reserva El Zapotal.
            </p>

            <div class="mt-8 flex flex-wrap gap-3" data-revelar="abajo">
                @auth('cliente')
                    <a href="{{ route('compras.crear') }}" class="btn-primary">Comprar boletos</a>
                    <a href="{{ route('compras.index') }}"
                       class="btn border-2 border-white/25 text-white hover:bg-white/10">Mis compras</a>
                @else
                    <a href="{{ route('portal.registro') }}" class="btn-primary">Comprar boletos</a>
                    <a href="{{ route('portal.ingresar') }}"
                       class="btn border-2 border-white/25 text-white hover:bg-white/10">Ya tengo cuenta</a>
                @endauth
            </div>

            <p class="mt-8 text-xs text-white/50" data-revelar="abajo">
                Martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.
            </p>
        </div>
    </section>

    {{-- ── Los dos jaguares ────────────────────────────────────────────
         El bloque que da personalidad al portal. Entran uno por cada lado al
         hacer scroll, y no son adorno: son el mismo animal, y decirlo corrige
         una confusión que casi todo el mundo trae. --}}
    {{-- `overflow-x-clip` no es decorativo: antes de revelarse, los jaguares
         están desplazados 2.5rem hacia los lados, y en pantallas angostas eso
         asomaba fuera del viewport y sacaba barra de scroll horizontal.
         `clip` en vez de `hidden` porque no crea contenedor de scroll y no
         rompe `position: sticky` de los hijos. --}}
    <section class="overflow-x-clip bg-texto pb-20 text-white sm:pb-28">
        <div class="mx-auto max-w-5xl px-4">

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['archivo' => 'jaguar-moteado', 'lado' => 'izquierda', 'encuadre' => '50% 42%',
                     'nombre'  => 'Jaguar', 'pie' => 'Panthera onca'],
                    ['archivo' => 'jaguar-negro',   'lado' => 'derecha',   'encuadre' => '50% 45%',
                     'nombre'  => 'Jaguar melánico', 'pie' => 'La misma especie'],
                ] as $gato)
                    <figure class="group relative overflow-hidden rounded-card"
                            data-revelar="{{ $gato['lado'] }}">
                        <picture>
                            <source srcset="{{ asset("images/{$gato['archivo']}.webp") }}" type="image/webp">
                            <img src="{{ asset("images/{$gato['archivo']}.jpg") }}"
                                 alt="{{ $gato['nombre'] }} en el ZooMAT"
                                 loading="lazy" decoding="async"
                                 class="h-72 w-full object-cover transition-transform duration-700
                                        group-hover:scale-105 sm:h-96"
                                 style="object-position: {{ $gato['encuadre'] }}">
                        </picture>

                        {{-- Degradado para que el texto se lea sobre cualquier
                             zona de la foto, clara u oscura. --}}
                        <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-5">
                            <p class="titulo text-sm">{{ $gato['nombre'] }}</p>
                            <p class="mt-0.5 text-xs text-white/60">{{ $gato['pie'] }}</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <div class="mx-auto mt-10 max-w-2xl text-center" data-revelar="abajo">
                <h2 class="titulo text-lg text-jade">Son el mismo animal</h2>
                <p class="mt-3 text-sm leading-relaxed text-white/70">
                    La «pantera negra» de América no es otra especie: es un jaguar melánico, con las
                    mismas manchas escondidas bajo el pelaje oscuro. Si te acercas con la luz
                    correcta, se alcanzan a ver.
                </p>
            </div>
        </div>
    </section>

@endsection

@section('contenido')

    {{-- ── Cifras ─────────────────────────────────────────────────────── --}}
    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-revelar="abajo">
        @foreach ([
            ['1942',  'Fundado por Miguel Álvarez del Toro'],
            ['1,400', 'Animales bajo cuidado'],
            ['170+',  'Especies, todas de Chiapas'],
            ['100',   'Hectáreas en El Zapotal'],
        ] as [$cifra, $glosa])
            <div class="card">
                <p class="titulo text-2xl text-jade">{{ $cifra }}</p>
                <p class="mt-1 text-xs leading-snug text-texto-suave">{{ $glosa }}</p>
            </div>
        @endforeach
    </section>

    {{-- ── Tarifas ────────────────────────────────────────────────────── --}}
    <section class="mt-16" data-revelar="abajo">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="titulo text-lg">Tarifas vigentes</h2>
                <p class="mt-1 text-sm text-texto-suave">
                    Compra en línea, recibe tu código QR por correo y preséntalo en el acceso. Sin filas.
                </p>
            </div>
            @auth('cliente')
                <a href="{{ route('compras.crear') }}" class="btn-primary btn-sm">Comprar</a>
            @endauth
        </div>

        @if ($rubros->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rubros as $rubro)
                    <div class="card transition-shadow hover:shadow-card">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="font-medium">{{ $rubro->tipo }}</p>
                            <p class="shrink-0 text-lg font-semibold tabular-nums
                                      {{ $rubro->esGratis() ? 'text-magenta' : 'text-jade' }}">
                                {{ $rubro->esGratis() ? 'Gratis' : $rubro->precioFormateado() }}
                            </p>
                        </div>
                        @if ($rubro->descripcion)
                            <p class="mt-2 text-xs leading-relaxed text-texto-suave">{{ $rubro->descripcion }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="card text-center">
                <p class="text-sm text-texto-suave">Todavía no hay tarifas publicadas. Vuelve pronto.</p>
            </div>
        @endif
    </section>

    {{-- ── Hábitats ───────────────────────────────────────────────────────
         PROVISIONAL. Cada tarjeta abre con una greca y el número de recorrido,
         a la espera de fotografía oficial del ZooMAT.

         Para poner la foto, sustituye TODO el contenido del div con la clase
         `h-36` por:

             <img src="{{ asset('images/habitats/vivario.jpg') }}"
                  alt="Vivario del ZooMAT" loading="lazy"
                  class="h-full w-full object-cover">

         El contenedor ya resuelve alto, recorte y esquinas; no hay que tocar
         nada más. --}}
    <section class="mt-16" data-revelar="abajo">
        <h2 class="titulo text-lg">Qué vas a ver</h2>
        <p class="mt-1 text-sm text-texto-suave">
            Seis recorridos dentro del zoológico, más de dos kilómetros de senderos en selva.
        </p>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Vivario', 'Serpientes, lagartijas y anfibios de la región, de cerca y en su ambiente.'],
                ['Herpetario', 'Reptiles vivos y el porqué de su papel en el equilibrio del ecosistema.'],
                ['Museo del Cocodrilo', 'El cocodrilo de pantano chiapaneco y su historia de casi cien millones de años.'],
                ['Museo Zoológico', 'La colección del naturalista que fundó todo esto, y la fauna que documentó en Chiapas.'],
                ['Aviarios y pajareras', 'Tucanes, guacamayas, pericos y el quetzal, símbolo de las montañas de Chiapas.'],
                ['Senderos en selva', 'Dos y medio kilómetros entre jabalíes, tepezcuintles, venados, coatíes y nutrias.'],
            ] as [$nombre, $descripcion])
                <article class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
                    {{-- MARCADOR PROVISIONAL — sustituir por foto oficial del ZooMAT.
                         Greca en zigzag, motivo de los textiles chiapanecos, al 15%
                         como sugiere el manual gráfico. El número ordena el recorrido.

                         El `id` del patrón lleva el índice del ciclo: seis <svg> en
                         la misma página con el mismo id harían que todas las tarjetas
                         usaran el patrón de la primera. --}}
                    <div class="relative flex h-36 items-center justify-center overflow-hidden bg-arena/20">
                        <svg class="absolute inset-0 h-full w-full text-jade" aria-hidden="true">
                            <defs>
                                <pattern id="greca-{{ $loop->index }}" width="26" height="26"
                                         patternUnits="userSpaceOnUse">
                                    <path d="M-2 20L5 13l7 7 7-7 7 7 7-7" fill="none"
                                          stroke="currentColor" stroke-width="2" opacity="0.15"></path>
                                    <path d="M-2 7L5 0l7 7 7-7 7 7 7-7" fill="none"
                                          stroke="currentColor" stroke-width="2" opacity="0.15"></path>
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#greca-{{ $loop->index }})"></rect>
                        </svg>

                        <p class="titulo relative text-4xl text-jade/45">
                            {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </p>
                    </div>

                    <div class="p-5">
                        <p class="titulo text-xs">{{ $nombre }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-texto-suave">{{ $descripcion }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ── Grupos escolares ───────────────────────────────────────────────
         Dato del sitio oficial. Es una de las razones por las que la gente
         entra a buscar al ZooMAT, así que no puede vivir solo en el pie. --}}
    <section class="mt-16" data-revelar="abajo">
        <div class="card">
            <h2 class="titulo text-base">¿Vienes con un grupo escolar?</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-texto-suave">
                Educación Ambiental atiende visitas guiadas de preescolar a universidad, de martes a
                viernes de 9:30 a 14:30 hrs. El programa dura 40 minutos e incluye charlas, juegos
                didácticos, cuentos y teatro guiñol; después el grupo recorre el parque por su cuenta
                o con guía. Se solicita con oficio a
                <a href="mailto:atencionescolarzoomat@gmail.com"
                   class="text-jade hover:underline">atencionescolarzoomat@gmail.com</a>.
            </p>
        </div>
    </section>

    {{-- ── Cierre ─────────────────────────────────────────────────────── --}}
    <section class="mt-6" data-revelar="abajo">
        <div class="card flex flex-wrap items-center justify-between gap-5 border-jade/25 bg-jade-suave">
            <div>
                <h2 class="titulo text-base text-jade">Tu boleto, en tu celular</h2>
                <p class="mt-1.5 max-w-md text-sm text-texto-suave">
                    Eliges el día en el calendario, pagas en línea y te llega el código QR por correo.
                    Lo muestras en el acceso y entras.
                </p>
            </div>
            @auth('cliente')
                <a href="{{ route('compras.crear') }}" class="btn-primary">Comprar boletos</a>
            @else
                <a href="{{ route('portal.registro') }}" class="btn-primary">Crear cuenta</a>
            @endauth
        </div>
    </section>

    <script>
        /*
         * Aparición al hacer scroll.
         *
         * IntersectionObserver y no `animation-timeline: view()`, que sería
         * más corto pero todavía no lo entienden Safari ni Firefox. Cada
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
