@extends('layouts.interno')

@section('titulo', 'Catálogos')
@section('subtitulo', $plural)

@section('contenido')

    {{-- Selector de catálogo --}}
    <nav class="mb-6 flex flex-wrap gap-2">
        @foreach ($catalogos as $c)
            <a href="{{ route('admin.catalogos.index', $c['slug']) }}"
               class="rounded-[10px] px-4 py-2 text-xs font-semibold transition-colors
                      {{ $catalogo === $c['slug']
                          ? 'bg-jade text-white'
                          : 'bg-superficie text-texto-suave border border-borde hover:border-jade hover:text-jade' }}">
                {{ $c['plural'] }}
            </a>
        @endforeach
    </nav>

    @can('editar_catalogos')
        <details class="card mb-6" @if ($errors->any()) open @endif>
            <summary class="titulo cursor-pointer text-sm text-jade">+ Agregar a {{ $plural }}</summary>

            <form method="POST" action="{{ route('admin.catalogos.store', $catalogo) }}"
                  class="mt-5 flex max-w-2xl flex-wrap items-end gap-3">
                @csrf

                @if ($catalogo === 'municipios')
                    <div class="min-w-48 flex-1">
                        <label class="label">Estado</label>
                        <select name="id_estado" class="input" required>
                            <option value="">— Seleccionar —</option>
                            @foreach ($estados as $e)
                                <option value="{{ $e->id }}" @selected(old('id_estado') == $e->id)>{{ $e->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="min-w-48 flex-1">
                    <label class="label">Nombre</label>
                    <input type="text" name="nombre" class="input" value="{{ old('nombre') }}" required>
                </div>

                @if ($catalogo === 'paises')
                    <div class="w-28">
                        <label class="label">ISO</label>
                        <input type="text" name="iso" class="input uppercase" maxlength="3"
                               value="{{ old('iso') }}" placeholder="MEX">
                    </div>
                @endif

                <button type="submit" class="btn-primary">Agregar</button>
            </form>
        </details>
    @endcan

    <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="titulo bg-jade text-left text-[11px] text-white">
                        <th class="px-4 py-3">Nombre</th>
                        @if ($catalogo === 'municipios')
                            <th class="px-4 py-3">Estado</th>
                        @endif
                        @if ($catalogo === 'paises')
                            <th class="px-4 py-3">ISO</th>
                        @endif
                        <th class="px-4 py-3">Estado</th>
                        @can('editar_catalogos')
                            <th class="px-4 py-3">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $registro)
                        <tr class="border-b border-borde transition-colors last:border-0 hover:bg-jade/4">
                            <td class="px-4 py-2.5">
                                @can('editar_catalogos')
                                    <form method="POST" action="{{ route('admin.catalogos.update', [$catalogo, $registro->id]) }}"
                                          class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        @if ($catalogo === 'municipios')
                                            <input type="hidden" name="id_estado" value="{{ $registro->id_estado }}">
                                        @endif
                                        @if ($catalogo === 'paises')
                                            <input type="hidden" name="iso" value="{{ $registro->iso }}">
                                        @endif
                                        <input type="text" name="nombre" value="{{ $registro->nombre }}"
                                               class="input max-w-xs py-1.5 text-xs" required>
                                        <button type="submit" class="btn-outline btn-sm">Guardar</button>
                                    </form>
                                @else
                                    {{ $registro->nombre }}
                                @endcan
                            </td>
                            @if ($catalogo === 'municipios')
                                <td class="px-4 py-2.5 text-xs text-texto-suave">{{ $registro->estado->nombre }}</td>
                            @endif
                            @if ($catalogo === 'paises')
                                <td class="px-4 py-2.5 text-xs text-texto-suave">{{ $registro->iso ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-2.5">
                                <span class="{{ $registro->activo ? 'badge-activo' : 'badge-inactivo' }}">
                                    {{ $registro->activo ? 'activo' : 'inactivo' }}
                                </span>
                            </td>
                            @can('editar_catalogos')
                                <td class="px-4 py-2.5">
                                    <form method="POST" action="{{ route('admin.catalogos.toggle', [$catalogo, $registro->id]) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" class="btn-outline btn-sm">
                                            {{ $registro->activo ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-texto-suave">
                                Este catálogo está vacío.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $registros->links() }}
    </div>

    <p class="mt-4 text-xs text-texto-suave">
        Los catálogos no se eliminan: se desactivan. Un registro inactivo deja de ofrecerse en los
        formularios, pero sigue explicando las compras que ya lo usaron.
    </p>
@endsection
