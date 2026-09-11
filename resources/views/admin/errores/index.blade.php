@extends('layouts.interno')

@section('titulo', 'Fallos del sistema')
@section('subtitulo', 'Qué se rompió, cuántas veces y desde cuándo')

@section('contenido')

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p class="text-sm text-texto-suave">
            @if ($pendientes === 0)
                No hay fallos pendientes de revisar.
            @else
                <strong class="text-texto">{{ number_format($pendientes) }}</strong>
                {{ Str::plural('fallo', $pendientes) }} sin revisar.
            @endif
        </p>

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="text" name="q" value="{{ request('q') }}"
                   class="input py-1.5 text-xs" placeholder="Buscar por tipo, mensaje o ruta">

            <label class="flex items-center gap-2 text-xs text-texto-suave">
                <input type="checkbox" name="todos" value="1" class="accent-jade"
                       @checked($mostrandoTodos) onchange="this.form.submit()">
                Incluir atendidos
            </label>

            <button type="submit" class="btn-outline btn-sm">Filtrar</button>
        </form>
    </div>

    @if ($errores->isEmpty())
        <div class="card py-12 text-center">
            <p class="titulo text-sm text-jade">Todo en orden</p>
            <p class="mx-auto mt-3 max-w-sm text-sm text-texto-suave">
                @if ($mostrandoTodos)
                    No hay ningún fallo registrado.
                @else
                    No hay fallos pendientes. Marca la casilla de arriba para ver los ya atendidos.
                @endif
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="titulo bg-jade text-left text-[11px] text-white">
                            <th class="px-4 py-3">Fallo</th>
                            <th class="px-4 py-3">Dónde</th>
                            <th class="px-4 py-3 text-right">Veces</th>
                            <th class="px-4 py-3">Última vez</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($errores as $error)
                            <tr class="cursor-pointer border-b border-borde transition-colors last:border-0
                                       hover:bg-jade/4 {{ $error->estaAtendido() ? 'bg-arena/15' : '' }}"
                                onclick="window.location='{{ route('admin.errores.ver', $error) }}'">

                                <td class="px-4 py-2.5">
                                    <p class="font-medium">{{ $error->claseCorta() }}</p>
                                    <p class="mt-0.5 max-w-md truncate text-xs text-texto-suave">
                                        {{ $error->mensaje }}
                                    </p>
                                </td>

                                <td class="px-4 py-2.5 text-xs text-texto-suave">
                                    <p>{{ basename((string) $error->archivo) }}:{{ $error->linea }}</p>
                                    @if ($error->url)
                                        <p class="mt-0.5 max-w-48 truncate">{{ $error->metodo }} {{ $error->url }}</p>
                                    @endif
                                </td>

                                <td class="px-4 py-2.5 text-right tabular-nums
                                           {{ $error->ocurrencias > 10 ? 'font-semibold text-cinabrio' : '' }}">
                                    {{ number_format($error->ocurrencias) }}
                                </td>

                                <td class="px-4 py-2.5 text-xs">
                                    <p>{{ $error->ultima_vez->translatedFormat('d/m/Y H:i') }}</p>
                                    <p class="mt-0.5 text-texto-suave">{{ $error->ultima_vez->diffForHumans() }}</p>
                                </td>

                                <td class="px-4 py-2.5">
                                    @if ($error->estaAtendido())
                                        <span class="badge-inactivo">atendido</span>
                                    @else
                                        <span class="badge-especial">pendiente</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $errores->links() }}</div>
    @endif

    <p class="mt-6 text-xs text-texto-suave">
        Los fallos se agrupan por tipo: el mismo error repetido suma en «Veces» en lugar de
        crear un renglón nuevo. Los ya atendidos se purgan solos pasados
        {{ config('taquilla.errores.dias_retencion') }} días.
    </p>
@endsection
