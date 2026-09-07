@extends('layouts.interno')

@section('titulo', 'Estadísticas de visitantes')
@section('subtitulo', 'Quiénes entraron y de dónde vinieron')

@php
    // Barra proporcional al máximo de cada bloque: comparar dentro del
    // grupo es lo que interesa, no contra un total global.
    $barra = function ($coleccion) {
        $max = $coleccion->max('pases') ?: 1;
        return fn ($pases) => max(2, round($pases / $max * 100));
    };
@endphp

@section('contenido')

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Desde</label>
            <input type="date" name="desde" class="input" value="{{ $datos['desde'] }}">
        </div>
        <div>
            <label class="label">Hasta</label>
            <input type="date" name="hasta" class="input" value="{{ $datos['hasta'] }}">
        </div>
        <button type="submit" class="btn-primary">Generar</button>
        <button type="button" onclick="window.print()" class="btn-outline">Imprimir</button>
    </form>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Visitantes</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums text-jade">
                {{ number_format($datos['resumen']['pases']) }}
            </p>
        </div>
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Hombres</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($datos['resumen']['hombres']) }}</p>
        </div>
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Mujeres</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($datos['resumen']['mujeres']) }}</p>
        </div>
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Compradores distintos</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($datos['resumen']['clientes']) }}</p>
        </div>
    </div>

    @if ($datos['resumen']['pases'] === 0)
        <div class="card mt-6 text-center">
            <p class="text-sm text-texto-suave">
                No hay visitas registradas en el periodo. Solo se cuentan pases efectivamente
                consumidos en el acceso, no boletos vendidos.
            </p>
        </div>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-2">

            @foreach ([
                'Tipo de visitante' => $datos['por_subnacionalidad'],
                'Nacionalidad'      => $datos['por_nacionalidad'],
                'Tipo de acceso'    => $datos['por_tipo_acceso'],
                'Estado de origen'  => $datos['por_estado'],
                'Municipio (Chiapas)' => $datos['por_municipio'],
                'País de origen'    => $datos['por_pais'],
            ] as $titulo => $coleccion)
                <div class="card">
                    <p class="titulo mb-4 text-sm text-jade">{{ $titulo }}</p>

                    @forelse ($coleccion as $fila)
                        @php $ancho = $barra($coleccion)($fila->pases); @endphp
                        <div class="mb-3 last:mb-0">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate">{{ $fila->etiqueta }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ number_format($fila->pases) }}</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-borde">
                                <div class="h-full rounded-full bg-jade" style="width: {{ $ancho }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-texto-suave">Sin datos capturados para este corte.</p>
                    @endforelse
                </div>
            @endforeach

            <div class="card lg:col-span-2">
                <p class="titulo mb-4 text-sm text-jade">Visitantes por día</p>

                @php $barraDia = $barra($datos['por_dia']); @endphp
                @forelse ($datos['por_dia'] as $fila)
                    <div class="mb-3 last:mb-0">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span>{{ \Illuminate\Support\Carbon::parse($fila->dia)->translatedFormat('l d/m/Y') }}</span>
                            <span class="tabular-nums font-medium">{{ number_format($fila->pases) }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-borde">
                            <div class="h-full rounded-full bg-magenta" style="width: {{ $barraDia($fila->pases) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-texto-suave">Sin visitas en el periodo.</p>
                @endforelse
            </div>
        </div>
    @endif

    <p class="mt-6 text-xs text-texto-suave">
        La procedencia se toma de cada renglón de la compra, no del domicilio de quien pagó: un
        mismo comprador puede traer gente de distintos lugares.
    </p>
@endsection
