@extends('layouts.interno')

@section('titulo', 'Rubros de cobro')
@section('subtitulo', 'Tarifas vigentes del zoológico')

@section('contenido')

    @can('editar_rubros')
        <details class="card mb-6" @if ($errors->any()) open @endif>
            <summary class="titulo cursor-pointer text-sm text-jade">+ Nuevo rubro</summary>

            <form method="POST" action="{{ route('admin.rubros.store') }}"
                  class="mt-5 grid max-w-3xl gap-4 sm:grid-cols-2" data-form-rubro>
                @csrf

                <div class="sm:col-span-2">
                    <label class="label">Tipo <span class="text-cinabrio">*</span></label>
                    <input type="text" name="tipo" class="input" value="{{ old('tipo') }}"
                           placeholder="Ej. Adulto nacional" required>
                </div>

                <div class="sm:col-span-2">
                    <label class="label">Descripción a detalle</label>
                    <textarea name="descripcion" rows="2" class="input">{{ old('descripcion') }}</textarea>
                </div>

                <div>
                    <label class="label">Nacionalidad <span class="text-cinabrio">*</span></label>
                    <select name="id_nacionalidad" class="input" required>
                        <option value="">— Seleccionar —</option>
                        @foreach ($nacionalidades as $n)
                            <option value="{{ $n->id }}" @selected(old('id_nacionalidad') == $n->id)>{{ $n->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label">Subnacionalidad <span class="text-cinabrio">*</span></label>
                    <select name="id_subnacionalidad" class="input" required>
                        <option value="">— Seleccionar —</option>
                        @foreach ($subnacionalidades as $s)
                            <option value="{{ $s->id }}" @selected(old('id_subnacionalidad') == $s->id)>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label">Tipo de acceso <span class="text-cinabrio">*</span></label>
                    <select name="id_tipo_acceso" class="input" required
                            data-tipo-acceso data-gratis="{{ $idTipoGratis }}">
                        <option value="">— Seleccionar —</option>
                        @foreach ($tiposAcceso as $t)
                            <option value="{{ $t->id }}" @selected(old('id_tipo_acceso') == $t->id)>{{ $t->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label">Precio en pesos <span class="text-cinabrio">*</span></label>
                    <input type="number" name="precio" step="0.01" min="0" class="input"
                           value="{{ old('precio', '0.00') }}" required data-precio>
                    <p class="mt-1 text-[11px] text-texto-suave" data-aviso-gratis hidden>
                        Un rubro GRATIS siempre cuesta 0.
                    </p>
                </div>

                <div>
                    <label class="label">Vigente desde <span class="text-cinabrio">*</span></label>
                    <input type="date" name="vigente_desde" class="input"
                           value="{{ old('vigente_desde', now()->toDateString()) }}" required>
                </div>

                <div>
                    <label class="label">Vigente hasta</label>
                    <input type="date" name="vigente_hasta" class="input" value="{{ old('vigente_hasta') }}">
                    <p class="mt-1 text-[11px] text-texto-suave">Vacío = sin fecha de término.</p>
                </div>

                <div class="sm:col-span-2 flex items-center gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="activo" value="1" class="accent-jade" @checked(old('activo', true))>
                        Activo
                    </label>
                    <button type="submit" class="btn-primary ml-auto">Crear rubro</button>
                </div>
            </form>
        </details>
    @endcan

    @if ($rubros->isEmpty())
        <div class="card text-center">
            <p class="text-sm text-texto-suave">Todavía no hay rubros capturados.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="titulo bg-jade text-left text-[11px] text-white">
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Clasificación</th>
                            <th class="px-4 py-3">Acceso</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3">Vigencia</th>
                            <th class="px-4 py-3">Estado</th>
                            @canany(['editar_rubros'])
                                <th class="px-4 py-3">Acciones</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rubros as $rubro)
                            <tr class="border-b border-borde align-top transition-colors last:border-0 hover:bg-jade/4">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $rubro->tipo }}</p>
                                    @if ($rubro->descripcion)
                                        <p class="mt-0.5 max-w-xs text-xs text-texto-suave">{{ $rubro->descripcion }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-texto-suave">
                                    {{ $rubro->nacionalidad->nombre }}<br>
                                    {{ $rubro->subnacionalidad->nombre }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($rubro->esGratis())
                                        <span class="badge-especial">{{ $rubro->tipoAcceso->nombre }}</span>
                                    @else
                                        <span class="text-xs text-texto-suave">{{ $rubro->tipoAcceso->nombre }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">
                                    {{ $rubro->precioFormateado() }}
                                </td>
                                <td class="px-4 py-3 text-xs text-texto-suave">
                                    {{ $rubro->vigente_desde->format('d/m/Y') }}
                                    @if ($rubro->vigente_hasta)
                                        <br>al {{ $rubro->vigente_hasta->format('d/m/Y') }}
                                    @else
                                        <br><span class="text-texto-suave/70">sin término</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ $rubro->activo ? 'badge-activo' : 'badge-inactivo' }}">
                                        {{ $rubro->activo ? 'activo' : 'inactivo' }}
                                    </span>
                                </td>
                                @can('editar_rubros')
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('admin.rubros.toggle', $rubro) }}">
                                                @csrf @method('PUT')
                                                <button type="submit" class="btn-outline btn-sm">
                                                    {{ $rubro->activo ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.rubros.destroy', $rubro) }}"
                                                  onsubmit="return confirm('¿Eliminar el rubro «{{ $rubro->tipo }}»? Se conserva en la bitácora y en las compras históricas.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="mt-4 text-xs text-texto-suave">
        Cambiar un precio no altera compras anteriores: el importe se congela al momento de comprar.
    </p>

    <script>
        // Un rubro GRATIS no puede tener precio. El servidor lo valida igual;
        // esto solo evita que el usuario capture algo que va a ser rechazado.
        document.querySelectorAll('[data-form-rubro]').forEach((form) => {
            const selector = form.querySelector('[data-tipo-acceso]');
            const precio   = form.querySelector('[data-precio]');
            const aviso    = form.querySelector('[data-aviso-gratis]');
            if (!selector || !precio) return;

            const sincronizar = () => {
                const esGratis = selector.value === selector.dataset.gratis;
                if (esGratis) {
                    precio.value = '0.00';
                }
                // readOnly y no disabled: un campo deshabilitado no se envía.
                precio.readOnly = esGratis;
                precio.classList.toggle('opacity-60', esGratis);
                if (aviso) aviso.hidden = !esGratis;
            };

            selector.addEventListener('change', sincronizar);
            sincronizar();
        });
    </script>
@endsection
