@extends('layouts.interno')

@section('titulo', 'Bitácora de accesos')
@section('subtitulo', 'Historial de escaneos en el acceso')

@section('contenido')

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Resultado</label>
            <select name="resultado" class="input min-w-36">
                <option value="">Todos</option>
                <option value="permitido" @selected(request('resultado') === 'permitido')>Permitidos</option>
                <option value="rechazado" @selected(request('resultado') === 'rechazado')>Rechazados</option>
            </select>
        </div>
        <div>
            <label class="label">Torniquete</label>
            <select name="torniquete" class="input min-w-32">
                <option value="">Todos</option>
                @foreach ($torniquetes as $t)
                    <option value="{{ $t }}" @selected(request('torniquete') === $t)>{{ $t }}</option>
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
        @if (request()->hasAny(['resultado', 'torniquete', 'desde', 'hasta']))
            <a href="{{ route('accesos.bitacora') }}" class="btn-outline">Limpiar</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="titulo bg-jade text-left text-[11px] text-white">
                        <th class="px-4 py-3">Momento</th>
                        <th class="px-4 py-3">Resultado</th>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Visitante</th>
                        <th class="px-4 py-3 text-center">Pases</th>
                        <th class="px-4 py-3">Método</th>
                        <th class="px-4 py-3">Torniquete</th>
                        <th class="px-4 py-3">Operador</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accesos as $acceso)
                        <tr class="border-b border-borde transition-colors last:border-0 hover:bg-jade/4">
                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-texto-suave">
                                {{ $acceso->escaneado_en->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($acceso->fuePermitido())
                                    <span class="badge-activo">permitido</span>
                                @else
                                    <span class="badge-inactivo">rechazado</span>
                                    @if ($acceso->motivo_rechazo)
                                        <p class="mt-1 text-[11px] text-texto-suave">
                                            {{ str_replace('_', ' ', $acceso->motivo_rechazo) }}
                                        </p>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($acceso->compra)
                                    @can('gestion_clientes')
                                        <a href="{{ route('admin.compras.ver', $acceso->compra) }}"
                                           class="text-jade hover:underline">{{ $acceso->compra->folio }}</a>
                                    @else
                                        {{ $acceso->compra->folio }}
                                    @endcan
                                @else
                                    <span class="text-xs text-texto-suave italic">código sin compra</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs">
                                {{ $acceso->compra?->cliente?->nombreCompleto() ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center tabular-nums">{{ $acceso->pases_consumidos }}</td>
                            <td class="px-4 py-2.5">
                                @if ($acceso->metodo === \App\Models\Acceso::METODO_FOLIO)
                                    {{-- Autorizada por criterio del operador, sin firma criptográfica --}}
                                    <span class="badge-especial">manual</span>
                                @else
                                    <span class="text-xs text-texto-suave">QR</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-texto-suave">{{ $acceso->id_torniquete }}</td>
                            <td class="px-4 py-2.5 text-xs text-texto-suave">
                                {{ $acceso->cuenta?->usuario?->nombre ?? 'desatendido' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-texto-suave">
                                No hay escaneos que coincidan con el filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $accesos->links() }}</div>

    <p class="mt-4 text-xs text-texto-suave">
        Se registran todos los escaneos, incluidos los rechazados: un código falsificado no
        corresponde a ninguna compra, pero el intento queda asentado.
    </p>
@endsection
