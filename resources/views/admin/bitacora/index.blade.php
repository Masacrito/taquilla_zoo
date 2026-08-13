@extends('layouts.interno')

@section('titulo', 'Bitácora de auditoría')
@section('subtitulo', 'Registro de operaciones sensibles')

@section('contenido')

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Tabla</label>
            <select name="tabla" class="input min-w-40">
                <option value="">Todas</option>
                @foreach ($tablas as $t)
                    <option value="{{ $t }}" @selected(request('tabla') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Acción</label>
            <select name="accion" class="input min-w-32">
                <option value="">Todas</option>
                @foreach ($acciones as $a)
                    <option value="{{ $a }}" @selected(request('accion') === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Desde</label>
            <input type="date" name="desde" class="input" value="{{ request('desde') }}">
        </div>
        <div>
            <label class="label">Hasta</label>
            <input type="date" name="hasta" class="input" value="{{ request('hasta') }}">
        </div>
        <button type="submit" class="btn-primary">Filtrar</button>
        @if (request()->hasAny(['tabla', 'accion', 'desde', 'hasta']))
            <a href="{{ route('admin.bitacora.index') }}" class="btn-outline">Limpiar</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="titulo bg-jade text-left text-[11px] text-white">
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Responsable</th>
                        <th class="px-4 py-3">Tabla</th>
                        <th class="px-4 py-3">Acción</th>
                        <th class="px-4 py-3">Registro</th>
                        <th class="px-4 py-3">Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movimientos as $mov)
                        <tr class="border-b border-borde align-top transition-colors last:border-0 hover:bg-jade/4">
                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-texto-suave">
                                {{ $mov->fecha->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-2.5 text-xs">
                                @if ($mov->cuenta?->usuario)
                                    <p class="font-medium">{{ $mov->cuenta->usuario->nombre }}</p>
                                    <p class="text-texto-suave">{{ $mov->cuenta->username }}</p>
                                @else
                                    <span class="text-texto-suave italic">sistema</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs">{{ $mov->tabla }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $clase = match ($mov->accion) {
                                        'CREATE' => 'badge-activo',
                                        'DELETE' => 'badge-inactivo',
                                        default  => 'badge-especial',
                                    };
                                @endphp
                                <span class="{{ $clase }}">{{ $mov->accion }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-texto-suave">{{ $mov->registro_id ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @if ($mov->detalles)
                                    <details>
                                        <summary class="cursor-pointer text-xs text-jade">Ver</summary>
                                        <pre class="mt-2 max-w-md overflow-x-auto rounded-input bg-fondo p-3 text-[11px] leading-relaxed">{{ json_encode($mov->detalles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    <span class="text-xs text-texto-suave">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-texto-suave">
                                No hay movimientos que coincidan con el filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>

    <p class="mt-4 text-xs text-texto-suave">
        Esta tabla es de solo inserción: los movimientos no se editan ni se eliminan.
    </p>
@endsection
