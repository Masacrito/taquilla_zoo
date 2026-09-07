@extends('layouts.publico')

@section('titulo', 'Comprar boletos')

@section('contenido')
    <h1 class="titulo mb-1 text-lg">Comprar boletos</h1>
    <p class="mb-6 text-sm text-texto-suave">
        Elige la fecha de tu visita y cuántas personas van en cada tarifa.
    </p>

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

                <p class="mt-2 text-xs text-texto-suave">
                    Martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.
                </p>
            </div>

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

                            <div class="flex gap-3">
                                <div class="w-24">
                                    <label class="label">Hombres</label>
                                    <input type="number" min="0" max="100" value="0" class="input text-center"
                                           name="renglones[{{ $i }}][cant_hombre]" data-cantidad>
                                </div>
                                <div class="w-24">
                                    <label class="label">Mujeres</label>
                                    <input type="number" min="0" max="100" value="0" class="input text-center"
                                           name="renglones[{{ $i }}][cant_mujer]" data-cantidad>
                                </div>
                            </div>
                        </div>

                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs text-jade">Procedencia (opcional)</summary>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <select name="renglones[{{ $i }}][id_pais]" class="input text-xs">
                                    <option value="">País</option>
                                    @foreach ($paises as $p)
                                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                    @endforeach
                                </select>
                                <select name="renglones[{{ $i }}][id_estado]" class="input text-xs">
                                    <option value="">Estado</option>
                                    @foreach ($estados as $e)
                                        <option value="{{ $e->id }}">{{ $e->nombre }}</option>
                                    @endforeach
                                </select>
                                <select name="renglones[{{ $i }}][id_municipio]" class="input text-xs">
                                    <option value="">Municipio</option>
                                    @foreach ($municipios as $m)
                                        <option value="{{ $m->id }}" data-estado="{{ $m->id_estado }}">{{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </details>
                    </div>
                @endforeach
            </div>

            <div class="card mt-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="titulo text-xs text-texto-suave">Total estimado</p>
                    <p class="text-2xl font-semibold tabular-nums" data-total>$0.00</p>
                    <p class="text-xs text-texto-suave">
                        <span data-pases>0</span> pases. El monto definitivo lo calcula el servidor.
                    </p>
                </div>
                <button type="submit" class="btn-primary">Continuar al pago</button>
            </div>
        </form>

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

                form.addEventListener('input', recalcular);

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
