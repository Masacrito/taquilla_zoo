@extends('layouts.publico')

@section('titulo', 'Comprar boletos')

@section('contenido')
    <h1 class="titulo text-lg">Comprar boletos</h1>
    <p class="mt-1 text-sm text-texto-suave">
        Elige la fecha de tu visita y cuántas personas van en cada tarifa.
    </p>

    @if ($correoInvitado)
        <p class="mt-3 inline-block rounded-badge bg-jade-suave px-3 py-1.5 text-xs text-jade">
            Compras como invitado. Tus boletos llegarán a <strong>{{ $correoInvitado }}</strong>.
        </p>
    @endif

    {{-- Tres pasos, para que se entienda dónde termina esto y qué falta. El
         tercero se marca distinto: ocurre fuera del sitio, en el banco. --}}
    <ol class="mb-8 mt-5 flex flex-wrap gap-x-6 gap-y-2 text-xs text-texto-suave">
        @foreach ([['1', 'Fecha'], ['2', 'Boletos'], ['3', 'Pago en línea']] as [$n, $paso])
            <li class="flex items-center gap-2">
                <span class="titulo flex h-6 w-6 items-center justify-center rounded-full
                             {{ $n === '3' ? 'bg-borde text-texto-suave' : 'bg-jade text-white' }}
                             text-[10px]">{{ $n }}</span>
                {{ $paso }}
            </li>
        @endforeach
    </ol>

    @if (! $hayDiasAbiertos)
        <div class="card text-center">
            <p class="text-sm text-texto-suave">
                No hay fechas disponibles para venta en este momento.
            </p>
        </div>
    @elseif ($rubros->isEmpty())
        <div class="card text-center">
            <p class="text-sm text-texto-suave">
                Todavía no hay tarifas publicadas.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('compras.guardar') }}" data-form-compra>
            @csrf

            <div class="card mb-6" data-calendario>
                <label class="label">Fecha de visita</label>

                {{-- El valor viaja aquí; los botones de abajo solo lo escriben. --}}
                <input type="hidden" name="fecha_visita" data-fecha-visita
                       value="{{ old('fecha_visita', $fechaElegida) }}">

                <div class="mt-2 max-w-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <button type="button" data-mes-anterior
                                class="rounded-[10px] px-3 py-1.5 text-lg leading-none text-jade
                                       transition-colors hover:bg-jade/10 disabled:opacity-30"
                                aria-label="Mes anterior">&lsaquo;</button>

                        <p class="titulo text-sm" data-mes-titulo></p>

                        <button type="button" data-mes-siguiente
                                class="rounded-[10px] px-3 py-1.5 text-lg leading-none text-jade
                                       transition-colors hover:bg-jade/10 disabled:opacity-30"
                                aria-label="Mes siguiente">&rsaquo;</button>
                    </div>

                    <div class="mb-1 grid grid-cols-7 gap-1 text-center text-[11px] text-texto-suave">
                        @foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $inicial)
                            <span>{{ $inicial }}</span>
                        @endforeach
                    </div>

                    @foreach ($meses as $mes)
                        <div class="grid grid-cols-7 gap-1" data-mes="{{ $mes['clave'] }}" data-titulo="{{ $mes['titulo'] }}" hidden>
                            @foreach ($mes['celdas'] as $celda)
                                @if ($celda === null)
                                    <span></span>
                                @elseif ($celda['abierto'])
                                    <button type="button"
                                            data-dia="{{ $celda['fecha'] }}"
                                            class="aspect-square rounded-[10px] border border-borde text-sm
                                                   tabular-nums transition-colors
                                                   hover:border-jade hover:bg-jade/10
                                                   data-[elegido]:border-jade data-[elegido]:bg-jade
                                                   data-[elegido]:font-semibold data-[elegido]:text-white">
                                        {{ $celda['numero'] }}
                                    </button>
                                @else
                                    <span class="flex aspect-square items-center justify-center rounded-[10px]
                                                 text-sm tabular-nums text-texto-suave/40"
                                          title="No disponible">{{ $celda['numero'] }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-sm" data-fecha-elegida></p>

                {{-- El aviso va junto al calendario, no solo arriba de la página:
                     quien llega hasta el botón de pago ya no ve el encabezado. --}}
                <p class="mt-2 text-sm font-medium text-cinabrio" data-falta="fecha" role="alert"
                   @unless ($errors->has('fecha_visita')) hidden @endunless>
                    {{ $errors->first('fecha_visita') ?: 'Elige en el calendario el día de tu visita.' }}
                </p>

                <p class="mt-2 text-xs text-texto-suave">
                    Martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.
                </p>
            </div>

            <p class="titulo mb-3 text-xs text-texto-suave">¿Cuántas personas van?</p>

            <p class="mb-3 text-sm font-medium text-cinabrio" data-falta="personas" role="alert" hidden>
                Indica al menos una persona en alguna tarifa.
            </p>

            <div class="grid gap-3">
                @foreach ($rubros as $i => $rubro)
                    <div class="card">
                        <input type="hidden" name="renglones[{{ $i }}][id_rubro]" value="{{ $rubro->id }}">

                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-48 flex-1">
                                <p class="font-medium">{{ $rubro->tipo }}</p>
                                @if ($rubro->descripcion)
                                    <p class="mt-1 text-xs text-texto-suave">{{ $rubro->descripcion }}</p>
                                @endif
                                <p class="mt-2 text-sm font-semibold tabular-nums text-jade"
                                   data-precio="{{ $rubro->precio_centavos }}">
                                    {{ $rubro->esGratis() ? 'Gratis' : $rubro->precioFormateado() }}
                                </p>
                            </div>

                            {{-- old(): si el servidor devuelve el formulario con un
                                 error, lo capturado no se pierde. --}}
                            <div class="flex gap-3">
                                <x-cantidad nombre="renglones[{{ $i }}][cant_hombre]" etiqueta="Hombres"
                                            :valor="old('renglones.' . $i . '.cant_hombre', 0)" />
                                <x-cantidad nombre="renglones[{{ $i }}][cant_mujer]" etiqueta="Mujeres"
                                            :valor="old('renglones.' . $i . '.cant_mujer', 0)" />
                            </div>
                        </div>

                        <details class="mt-3" @if (old('renglones.' . $i . '.id_pais') || old('renglones.' . $i . '.id_estado')) open @endif>
                            <summary class="cursor-pointer text-xs text-jade">Procedencia (opcional)</summary>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <select name="renglones[{{ $i }}][id_pais]" class="input text-xs">
                                    <option value="">País</option>
                                    @foreach ($paises as $p)
                                        <option value="{{ $p->id }}" @selected(old('renglones.' . $i . '.id_pais') == $p->id)>{{ $p->nombre }}</option>
                                    @endforeach
                                </select>
                                <select name="renglones[{{ $i }}][id_estado]" class="input text-xs">
                                    <option value="">Estado</option>
                                    @foreach ($estados as $e)
                                        <option value="{{ $e->id }}" @selected(old('renglones.' . $i . '.id_estado') == $e->id)>{{ $e->nombre }}</option>
                                    @endforeach
                                </select>
                                <select name="renglones[{{ $i }}][id_municipio]" class="input text-xs">
                                    <option value="">Municipio</option>
                                    @foreach ($municipios as $m)
                                        <option value="{{ $m->id }}" data-estado="{{ $m->id_estado }}" @selected(old('renglones.' . $i . '.id_municipio') == $m->id)>{{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </details>
                    </div>
                @endforeach
            </div>

            {{-- El resumen se queda pegado al borde inferior mientras se
                 capturan cantidades: con cinco tarifas y la procedencia
                 desplegada, el botón quedaba fuera de pantalla en el celular y
                 había que volver a bajar hasta el final para pagar. --}}
            <div class="sticky bottom-0 z-10 -mx-4 mt-6 border-t border-borde bg-superficie/95
                        px-4 py-3 shadow-[0_-8px_24px_rgba(0,0,0,0.06)] backdrop-blur
                        sm:mx-0 sm:rounded-card sm:border sm:px-6">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="titulo text-[10px] text-texto-suave">Total estimado</p>
                        <p class="text-xl font-semibold tabular-nums text-jade sm:text-2xl" data-total>$0.00</p>
                        <p class="truncate text-[11px] text-texto-suave">
                            <span data-pases>0</span> pases · lo calcula el servidor
                        </p>
                    </div>
                    <button type="submit" class="btn-primary shrink-0 px-4 sm:px-6">
                        Continuar al pago
                    </button>
                </div>
            </div>
        </form>

        {{-- Las advertencias que el brief exige, donde de verdad se leen: junto
             a la decisión, no enterradas en el pie. --}}
        <div class="mt-8 rounded-card border border-borde bg-arena/15 p-5">
            <p class="titulo mb-3 text-[11px] text-texto-suave">Ten esto en cuenta</p>
            <ul class="space-y-1.5 text-xs leading-relaxed text-texto-suave">
                <li>El tipo de visitante se valida en el acceso. Si no corresponde, ahí se paga el boleto.</li>
                <li>Tercera edad presenta INAPAM y estudiante presenta credencial.</li>
                <li>Niño Pavón entra gratis hasta 1.20 m de estatura.</li>
                <li>El código QR llega por correo. Si no lo ves, revisa spam o correo no deseado.</li>
                <li>
                    Los boletos valen solo para la fecha elegida. Al pagar aceptas las
                    <a href="{{ route('portal.politicas') }}" target="_blank" rel="noopener"
                       class="text-jade underline-offset-2 hover:underline">políticas de entrega, cancelación y reembolso</a>.
                </li>
            </ul>
        </div>

        <script>
            // Estimación visual únicamente. El total que se cobra lo calcula
            // el servidor leyendo el catálogo; lo que se muestre aquí no
            // influye en el importe.
            (function () {
                const form = document.querySelector('[data-form-compra]');
                if (!form) return;

                // ── Calendario de fecha de visita ──────────────────────────
                // Los meses ya vienen pintados desde el servidor: aquí solo se
                // muestra uno a la vez y se escribe la fecha en el campo
                // oculto. Cambiar de mes no recarga, así que no se pierden las
                // cantidades ya capturadas.
                (function () {
                    const caja = form.querySelector('[data-calendario]');
                    if (!caja) return;

                    const campo    = caja.querySelector('[data-fecha-visita]');
                    const titulo   = caja.querySelector('[data-mes-titulo]');
                    const leyenda  = caja.querySelector('[data-fecha-elegida]');
                    const anterior = caja.querySelector('[data-mes-anterior]');
                    const siguiente = caja.querySelector('[data-mes-siguiente]');
                    const meses    = Array.from(caja.querySelectorAll('[data-mes]'));
                    if (!meses.length) return;

                    const titulos = meses.map((m) => m.dataset.mes);
                    let visible = 0;

                    // Los títulos legibles los pinta el servidor en el atributo
                    // data-titulo de cada mes.
                    const mostrarMes = (i) => {
                        visible = Math.min(Math.max(i, 0), meses.length - 1);
                        meses.forEach((m, j) => { m.hidden = j !== visible; });
                        titulo.textContent = meses[visible].dataset.titulo;
                        anterior.disabled = visible === 0;
                        siguiente.disabled = visible === meses.length - 1;
                    };

                    const formato = new Intl.DateTimeFormat('es-MX', {
                        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
                    });

                    const elegir = (fecha) => {
                        campo.value = fecha;
                        if (fecha) caja.querySelector('[data-falta="fecha"]').hidden = true;

                        caja.querySelectorAll('[data-dia]').forEach((b) => {
                            b.toggleAttribute('data-elegido', b.dataset.dia === fecha);
                        });

                        // Se construye a mediodía para que el desfase de zona
                        // horaria no recorra la fecha un día hacia atrás.
                        leyenda.textContent = fecha
                            ? 'Visita: ' + formato.format(new Date(fecha + 'T12:00:00'))
                            : '';
                    };

                    caja.addEventListener('click', (e) => {
                        const dia = e.target.closest('[data-dia]');
                        if (dia) elegir(dia.dataset.dia);
                    });

                    anterior.addEventListener('click', () => mostrarMes(visible - 1));
                    siguiente.addEventListener('click', () => mostrarMes(visible + 1));

                    // Si ya venía una fecha (por old() o por la URL), se abre
                    // en su mes y queda marcada.
                    const previa = campo.value;
                    const indice = previa ? titulos.indexOf(previa.slice(0, 7)) : -1;

                    mostrarMes(indice >= 0 ? indice : 0);
                    if (previa) elegir(previa);
                })();

                const salidaTotal = form.querySelector('[data-total]');
                const salidaPases = form.querySelector('[data-pases]');

                const recalcular = () => {
                    let centavos = 0;
                    let pases = 0;

                    form.querySelectorAll('.card').forEach((tarjeta) => {
                        const precio = tarjeta.querySelector('[data-precio]');
                        if (!precio) return;
                        const unitario = parseInt(precio.dataset.precio, 10) || 0;

                        tarjeta.querySelectorAll('[data-cantidad]').forEach((campo) => {
                            const n = parseInt(campo.value, 10) || 0;
                            centavos += unitario * n;
                            pases += n;
                        });
                    });

                    salidaTotal.textContent = '$' + (centavos / 100).toLocaleString('es-MX', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2,
                    });
                    salidaPases.textContent = pases;
                };

                // ── Cantidades ─────────────────────────────────────────────
                // Todo delegado en el formulario: un solo juego de listeners
                // para todos los selectores, sin importar cuántas tarifas haya.
                const esCantidad = (el) => el instanceof HTMLInputElement && el.matches('[data-cantidad]');
                const avisoPersonas = form.querySelector('[data-falta="personas"]');

                // Deja en el campo un entero dentro de sus límites. Vacío o
                // basura cuentan como 0.
                const fijar = (campo, n) => {
                    const limpio = Math.min(Math.max(parseInt(n, 10) || 0, 0), parseInt(campo.max, 10));
                    if (campo.value !== String(limpio)) campo.value = limpio;
                    return limpio;
                };

                const mover = (campo, paso) => {
                    fijar(campo, (parseInt(campo.value, 10) || 0) + paso);
                    campo.dispatchEvent(new Event('input', { bubbles: true }));
                };

                // Al entrar se selecciona el contenido: lo que se teclee
                // reemplaza al 0 en vez de quedar pegado a él («03»).
                form.addEventListener('focusin', (e) => { if (esCantidad(e.target)) e.target.select(); });

                // Al salir, el campo queda normalizado aunque se haya dejado vacío.
                form.addEventListener('focusout', (e) => {
                    if (esCantidad(e.target)) { fijar(e.target, e.target.value); recalcular(); }
                });

                form.addEventListener('input', (e) => {
                    // Mientras se teclea solo se quitan ceros a la izquierda y
                    // se respeta el tope; el vacío se deja para poder borrar.
                    if (esCantidad(e.target) && e.target.value !== '') fijar(e.target, e.target.value);
                    avisoPersonas.hidden = true;
                    recalcular();
                });

                form.addEventListener('click', (e) => {
                    const boton = e.target.closest('[data-paso]');
                    if (!boton) return;
                    mover(boton.closest('[data-selector-cantidad]').querySelector('[data-cantidad]'),
                          parseInt(boton.dataset.paso, 10));
                });

                // La rueda solo cuenta con el campo enfocado: si contara al
                // pasar el cursor, desplazar la página cambiaría boletos sin
                // querer. Las flechas ↑↓ las resuelve el navegador.
                form.addEventListener('wheel', (e) => {
                    if (!esCantidad(e.target) || document.activeElement !== e.target) return;
                    e.preventDefault();
                    mover(e.target, e.deltaY < 0 ? 1 : -1);
                }, { passive: false });

                // ── Antes de enviar ────────────────────────────────────────
                // El servidor valida de todos modos; esto solo evita el viaje
                // y señala qué falta justo donde falta.
                form.addEventListener('submit', (e) => {
                    const avisoFecha = form.querySelector('[data-falta="fecha"]');
                    const sinFecha = !form.querySelector('[data-fecha-visita]').value;
                    const sinPersonas = Array.from(form.querySelectorAll('[data-cantidad]'))
                        .map((campo) => fijar(campo, campo.value))
                        .every((n) => n === 0);

                    avisoFecha.hidden = !sinFecha;
                    avisoPersonas.hidden = !sinPersonas;

                    if (sinFecha || sinPersonas) {
                        e.preventDefault();
                        (sinFecha ? avisoFecha : avisoPersonas).scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });

                // Filtra municipios según el estado elegido en el mismo renglón.
                form.querySelectorAll('select[name*="[id_estado]"]').forEach((selEstado) => {
                    const contenedor = selEstado.closest('.grid');
                    const selMunicipio = contenedor?.querySelector('select[name*="[id_municipio]"]');
                    if (!selMunicipio) return;

                    const todas = Array.from(selMunicipio.options);

                    selEstado.addEventListener('change', () => {
                        const idEstado = selEstado.value;
                        selMunicipio.innerHTML = '';
                        todas
                            .filter((o) => o.value === '' || o.dataset.estado === idEstado)
                            .forEach((o) => selMunicipio.appendChild(o.cloneNode(true)));
                    });

                    selEstado.dispatchEvent(new Event('change'));
                });

                recalcular();
            })();
        </script>
    @endif
@endsection
