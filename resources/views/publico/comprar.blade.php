@extends('layouts.publico')

@section('titulo', 'Comprar boletos')

@section('contenido')
    <h1 class="titulo mb-1 text-lg">Comprar boletos</h1>
    <p class="mb-6 text-sm text-texto-suave">
        Elige la fecha de tu visita y cuántas personas van en cada tarifa.
    </p>

    @if ($diasDisponibles->isEmpty())
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

            <div class="card mb-6">
                <label class="label">Fecha de visita</label>
                <select name="fecha_visita" class="input max-w-xs" required>
                    <option value="">— Seleccionar —</option>
                    @foreach ($diasDisponibles as $dia)
                        <option value="{{ $dia->fecha->toDateString() }}"
                                @selected(old('fecha_visita', $fechaElegida) === $dia->fecha->toDateString())>
                            {{ $dia->fecha->translatedFormat('l d/m/Y') }}
                            — {{ number_format($dia->disponibles()) }} lugares
                        </option>
                    @endforeach
                </select>
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
