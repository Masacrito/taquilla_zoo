@extends('layouts.interno')

@section('titulo', 'Compras')
@section('subtitulo', 'Ventas en línea')

@section('contenido')

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <label class="label">Folio o correo</label>
            <input type="text" name="q" class="input" value="{{ $busqueda }}" placeholder="ZM-2026-000001">
        </div>
        <div>
            <label class="label">Estado</label>
            <select name="estado" class="input min-w-40">
                <option value="">Todos</option>
                @foreach ($estados as $e)
                    <option value="{{ $e }}" @selected(request('estado') === $e)>{{ str_replace('_', ' ', $e) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Visita desde</label>
            <input type="date" name="desde" class="input" value="{{ request('desde') }}">
        </div>
        <div>
            <label class="label">Hasta</label>
            <input type="date" name="hasta" class="input" value="{{ request('hasta') }}">
        </div>
        <button type="submit" class="btn-primary">Filtrar</button>
        @if (request()->hasAny(['q', 'estado', 'desde', 'hasta']))
            <a href="{{ route('admin.compras.index') }}" class="btn-outline">Limpiar</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="titulo bg-jade text-left text-[11px] text-white">
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Visitante</th>
                        <th class="px-4 py-3">Visita</th>
                        <th class="px-4 py-3 text-center">Pases</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($compras as $compra)
                        <tr class="border-b border-borde transition-colors last:border-0 hover:bg-jade/4">
                            <td class="px-4 py-2.5 font-medium">{{ $compra->folio }}</td>
                            <td class="px-4 py-2.5">
                                <p>{{ $compra->cliente->nombreCompleto() }}</p>
                                <p class="text-xs text-texto-suave">{{ $compra->cliente->correo }}</p>
                            </td>
                            <td class="px-4 py-2.5">{{ $compra->fecha_visita->translatedFormat('d/m/Y') }}</td>
                            <td class="px-4 py-2.5 text-center tabular-nums">
                                {{ $compra->pases_usados }}/{{ $compra->pases_total }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $compra->totalFormateado() }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $clase = match (true) {
                                        $compra->estaPagada() => 'badge-activo',
                                        $compra->estado === \App\Models\Compra::PENDIENTE_PAGO => 'badge-especial',
                                        default => 'badge-inactivo',
                                    };
                                @endphp
                                <span class="{{ $clase }}">{{ str_replace('_', ' ', $compra->estado) }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.compras.ver', $compra) }}" class="btn-outline btn-sm">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-texto-suave">
                                No hay compras que coincidan con el filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $compras->links() }}</div>
@endsection
