@extends('layouts.interno')

@section('titulo', 'Calendario de operación')
@section('subtitulo', 'Días de apertura · martes a domingo, 8:30 a 16:00')

@section('contenido')

    <div class="card mb-6">
        <p class="titulo mb-4 text-sm text-jade">Generar días</p>

        <form method="POST" action="{{ route('admin.aforo.generar') }}"
              class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="label">Desde</label>
                <input type="date" name="desde" class="input"
                       value="{{ old('desde', $desde->toDateString()) }}" required>
            </div>
            <div>
                <label class="label">Hasta</label>
                <input type="date" name="hasta" class="input"
                       value="{{ old('hasta', $hasta->toDateString()) }}" required>
            </div>
            <button type="submit" class="btn-primary">Generar</button>
        </form>

        <p class="mt-3 text-xs text-texto-suave">
            El sistema mantiene abiertos por su cuenta los próximos
            {{ config('taquilla.calendario.meses_a_la_venta') }} meses; este formulario es para abrir
            rangos fuera de eso. Los lunes se crean cerrados y los días que ya existan no se modifican,
            así que puedes ejecutarlo sin miedo a pisar cierres puestos a mano.
        </p>
    </div>

    {{-- Solo existe mientras haya algo que revisar: el mismo criterio que el
         aviso del tablero, que es el que trae hasta aquí. --}}
    @if ($pendientes > 0)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-card border
                    border-jade/25 bg-jade-suave px-5 py-3.5">
            <p class="text-sm text-jade">
                {{ $pendientes === 1 ? 'Hay 1 día generado' : "Hay {$pendientes} días generados" }}
                automáticamente sin revisar en este rango. Cierra los que no abran y márcalos como revisados.
            </p>
            <form method="POST" action="{{ route('admin.aforo.revisar') }}">
                @csrf @method('PUT')
                <input type="hidden" name="desde" value="{{ $desde->toDateString() }}">
                <input type="hidden" name="hasta" value="{{ $hasta->toDateString() }}">
                <button type="submit" class="btn-primary btn-sm">Marcar como revisados</button>
            </form>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Ver desde</label>
            <input type="date" name="desde" class="input" value="{{ $desde->toDateString() }}">
        </div>
        <div>
            <label class="label">Hasta</label>
            <input type="date" name="hasta" class="input" value="{{ $hasta->toDateString() }}">
        </div>
        <button type="submit" class="btn-outline">Filtrar</button>
    </form>

    @if ($dias->isEmpty())
        <div class="card text-center">
            <p class="text-sm text-texto-suave">
                No hay días generados en este rango. Usa el formulario de arriba.
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="titulo bg-jade text-left text-[11px] text-white">
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Motivo del cierre</th>
                            <th class="px-4 py-3">Boletos vigentes</th>
                            <th class="px-4 py-3">Ajustar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dias as $dia)
                            <tr class="border-b border-borde transition-colors last:border-0 hover:bg-jade/4
                                       {{ $dia->cerrado ? 'bg-arena/15' : '' }}">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium">{{ $dia->fecha->translatedFormat('d/m/Y') }}</p>
                                    <p class="text-xs text-texto-suave">{{ $dia->fecha->translatedFormat('l') }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    @if ($dia->cerrado)
                                        <span class="badge-inactivo">cerrado</span>
                                    @else
                                        <span class="badge-activo">abierto</span>
                                    @endif
                                    @if ($dia->esAutomatico())
                                        <span class="badge-especial"
                                              title="{{ $dia->pendienteDeRevision() ? 'Generado por el sistema, sin revisar' : 'Generado por el sistema' }}">
                                            automático{{ $dia->pendienteDeRevision() ? ' · sin revisar' : '' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-texto-suave">
                                    {{ $dia->motivo_cierre ?: '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-xs tabular-nums text-texto-suave">
                                    @if ($fila = $vendido->get($dia->fecha->toDateString()))
                                        {{ $fila->compras }} {{ $fila->compras == 1 ? 'compra' : 'compras' }}
                                        · {{ $fila->pases }} {{ $fila->pases == 1 ? 'pase' : 'pases' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <details>
                                        <summary class="cursor-pointer text-xs text-jade">Editar</summary>
                                        <form method="POST"
                                              action="{{ route('admin.aforo.update', $dia->fecha->toDateString()) }}"
                                              class="mt-2 grid max-w-xs gap-2">
                                            @csrf @method('PUT')
                                            <label class="flex items-center gap-2 text-xs">
                                                <input type="checkbox" name="cerrado" value="1"
                                                       class="accent-jade" @checked($dia->cerrado)>
                                                Cerrado
                                            </label>
                                            <input type="text" name="motivo_cierre" class="input py-1.5 text-xs"
                                                   value="{{ $dia->motivo_cierre }}" placeholder="Motivo del cierre">
                                            <button type="submit" class="btn-primary btn-sm">Guardar</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="mt-4 text-xs text-texto-suave">
        No hay cupo máximo ni mínimo: la venta de un día abierto es ilimitada. Cerrar un día solo
        impide comprar boletos nuevos para esa fecha; las compras ya emitidas no se tocan. La columna
        «Boletos vigentes» dice a cuánta gente habría que avisar.
    </p>
@endsection
