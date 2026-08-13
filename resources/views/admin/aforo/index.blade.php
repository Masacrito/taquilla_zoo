@extends('layouts.interno')

@section('titulo', 'Aforo diario')
@section('subtitulo', 'Cupo por día · martes a domingo, 8:30 a 16:00')

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
            <div>
                <label class="label">Cupo máximo</label>
                <input type="number" name="cupo_maximo" min="0" class="input w-32"
                       value="{{ old('cupo_maximo', $cupoPorOmision) }}" required>
            </div>
            <button type="submit" class="btn-primary">Generar</button>
        </form>

        <p class="mt-3 text-xs text-texto-suave">
            Los lunes se crean cerrados automáticamente. Los días que ya existan no se modifican,
            así que puedes ejecutarlo sin miedo a pisar cupos ajustados a mano.
        </p>
    </div>

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
                            <th class="px-4 py-3 text-right">Cupo</th>
                            <th class="px-4 py-3 text-right">Reservados</th>
                            <th class="px-4 py-3 text-right">Disponibles</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Ajustar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dias as $dia)
                            @php
                                $ocupacion = $dia->cupo_maximo > 0
                                    ? round($dia->reservados / $dia->cupo_maximo * 100)
                                    : 0;
                            @endphp
                            <tr class="border-b border-borde transition-colors last:border-0 hover:bg-jade/4
                                       {{ $dia->cerrado ? 'bg-arena/15' : '' }}">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium">{{ $dia->fecha->translatedFormat('d/m/Y') }}</p>
                                    <p class="text-xs text-texto-suave">{{ $dia->fecha->translatedFormat('l') }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($dia->cupo_maximo) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ number_format($dia->reservados) }}
                                    <span class="text-xs text-texto-suave">({{ $ocupacion }}%)</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium tabular-nums">
                                    {{ number_format($dia->disponibles()) }}
                                </td>
                                <td class="px-4 py-2.5">
                                    @if ($dia->cerrado)
                                        <span class="badge-inactivo">cerrado</span>
                                        @if ($dia->motivo_cierre)
                                            <p class="mt-1 max-w-40 text-[11px] text-texto-suave">{{ $dia->motivo_cierre }}</p>
                                        @endif
                                    @else
                                        <span class="badge-activo">abierto</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <details>
                                        <summary class="cursor-pointer text-xs text-jade">Editar</summary>
                                        <form method="POST"
                                              action="{{ route('admin.aforo.update', $dia->fecha->toDateString()) }}"
                                              class="mt-2 grid max-w-xs gap-2">
                                            @csrf @method('PUT')
                                            <input type="number" name="cupo_maximo" min="{{ $dia->reservados }}"
                                                   class="input py-1.5 text-xs" value="{{ $dia->cupo_maximo }}" required>
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
        El cupo no puede bajarse por debajo de los pases ya reservados: dejaría compras pagadas sin lugar.
    </p>
@endsection
