@extends('layouts.interno')

@section('titulo', 'Rubros de cobro')
@section('subtitulo', 'Tarifas vigentes del zoológico')

@section('contenido')

    @can('editar_rubros')
        {{-- Solo se abre con errores del alta: los de una edición abren su fila. --}}
        <details class="card mb-6" @if ($errors->any() && ! old('_rubro')) open @endif>
            <summary class="titulo cursor-pointer text-sm text-jade">+ Nuevo rubro</summary>

            <div class="mt-5">
                @include('admin.rubros._formulario', ['rubro' => null])
            </div>
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
                                            <button type="button" class="btn-outline btn-sm" data-editar="{{ $rubro->id }}">
                                                Editar
                                            </button>
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
                            @can('editar_rubros')
                                {{-- Fila de edición: oculta hasta pulsar «Editar», o
                                     abierta si este rubro volvió con errores. --}}
                                <tr class="border-b border-borde bg-fondo" data-edicion="{{ $rubro->id }}"
                                    @unless ($errors->any() && old('_rubro') == $rubro->id) hidden @endunless>
                                    <td colspan="7" class="px-4 py-5">
                                        <p class="titulo mb-4 text-xs text-jade">Editar «{{ $rubro->tipo }}»</p>
                                        @include('admin.rubros._formulario', ['rubro' => $rubro])
                                    </td>
                                </tr>
                            @endcan
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="mt-4 text-xs text-texto-suave">
        Editar un rubro o cambiar su precio no altera compras anteriores: el nombre y el importe se
        congelan al momento de comprar.
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

        // «Editar» muestra u oculta la fila de edición de ese rubro.
        document.querySelectorAll('[data-editar]').forEach((boton) => {
            boton.addEventListener('click', () => {
                const fila = document.querySelector(`[data-edicion="${boton.dataset.editar}"]`);
                fila.hidden = !fila.hidden;
            });
        });
    </script>
@endsection
