@extends('layouts.interno')

@section('titulo', 'Panel taquilla')
@section('subtitulo', 'Bienvenido, ' . auth()->user()->usuario->nombre)

@section('contenido')

    {{-- ── Estado del día ──────────────────────────────────────────────────
         Lo primero que necesita saber quien está en la caseta. Si el
         zoológico no abre hoy, todo lo demás sobra. --}}
    @if (! $tablero['abierto'])
        <div class="mb-6 rounded-card border border-cinabrio/25 bg-cinabrio-suave px-5 py-4">
            <p class="titulo text-sm text-cinabrio">Hoy no se abre</p>
            <p class="mt-2 text-sm text-cinabrio">
                @if (! $tablero['en_calendario'])
                    Esta fecha no está dada de alta en el calendario de operación.
                @elseif ($tablero['motivo_cierre'])
                    {{ $tablero['motivo_cierre'] }}
                @else
                    El día está marcado como cerrado.
                @endif
            </p>
        </div>
    @endif

    {{-- ── La acción principal ─────────────────────────────────────────────
         Escanear es el trabajo del turno, no una tarjeta más en una rejilla.
         Va grande y arriba, pensada para una tablet en la entrada. --}}
    @can('validar_accesos')
        <a href="{{ route('accesos.escanear') }}"
           class="group mb-8 flex items-center justify-between gap-5 rounded-card bg-jade
                  px-6 py-6 text-white shadow-soft transition-opacity hover:opacity-90">
            <div>
                <p class="titulo text-lg">Escanear acceso</p>
                <p class="mt-1 text-sm text-white/75">
                    Lectura del código QR en la entrada
                </p>
            </div>
            <span class="shrink-0 text-3xl transition-transform group-hover:translate-x-1"
                  aria-hidden="true">&rsaquo;</span>
        </a>
    @endcan

    {{-- ── El día ──────────────────────────────────────────────────────────
         Números grandes: esto se lee de reojo entre visitante y visitante. --}}
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 class="titulo text-sm">Hoy</h2>
        <p class="text-xs text-texto-suave">{{ now()->translatedFormat('l d \d\e F') }}</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="card">
            <p class="titulo text-[11px] text-texto-suave">Esperados</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">
                {{ number_format($tablero['pases_esperados']) }}
            </p>
            <p class="mt-1 text-xs text-texto-suave">boletos para hoy</p>
        </div>

        <div class="card">
            <p class="titulo text-[11px] text-texto-suave">Ya entraron</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums text-jade">
                {{ number_format($tablero['ya_entraron']) }}
            </p>
            @php
                $faltan = max(0, $tablero['pases_esperados'] - $tablero['ya_entraron']);
            @endphp
            <p class="mt-1 text-xs text-texto-suave">
                @if ($tablero['pases_esperados'] === 0)
                    sin boletos para hoy
                @else
                    faltan {{ number_format($faltan) }} por llegar
                @endif
            </p>
        </div>

        {{-- Los rechazos son el problema inmediato de quien está en la
             puerta: casi siempre es un QR de otra fecha. Si empiezan a
             acumularse, algo está pasando. --}}
        <div class="card {{ $tablero['rechazos'] > 0 ? 'border-cinabrio/25' : '' }}">
            <p class="titulo text-[11px] text-texto-suave">Rechazados</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums
                      {{ $tablero['rechazos'] > 0 ? 'text-cinabrio' : '' }}">
                {{ number_format($tablero['rechazos']) }}
            </p>
            <p class="mt-1 text-xs text-texto-suave">
                @if ($tablero['rechazos'] === 0)
                    ningún escaneo rechazado
                @else
                    revisa la bitácora de entradas
                @endif
            </p>
        </div>
    </div>

    {{-- ── El corte ────────────────────────────────────────────────────────
             `generar_cortes` sí está entre los permisos de Taquilla. --}}
    @can('generar_cortes')
        <div class="card mt-3 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="titulo text-[11px] text-texto-suave">Cobrado hoy</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-jade">
                    ${{ number_format($tablero['vendido_centavos'] / 100, 2) }}
                </p>
                <p class="mt-1 text-xs text-texto-suave">
                    {{ $tablero['compras'] }} {{ Str::plural('compra', $tablero['compras']) }} en línea
                </p>
            </div>
            <a href="{{ route('admin.cortes') }}" class="btn-outline btn-sm">Ver el corte</a>
        </div>
    @endcan

    {{-- ── Lo demás ────────────────────────────────────────────────────────
         Solo lo que este rol alcanza. Nada de estadísticas, catálogos ni
         fallos del sistema: no tiene permiso y tampoco podría atenderlos. --}}
    <div class="mt-8 grid gap-3 sm:grid-cols-2">
        @can('ver_bitacora_accesos')
            <a href="{{ route('accesos.bitacora') }}" class="card transition-shadow hover:shadow-card">
                <p class="titulo text-sm text-jade">Entradas</p>
                <p class="mt-2 text-xs leading-relaxed text-texto-suave">
                    Todo escaneo registrado del día, aceptado o rechazado, con su motivo.
                </p>
            </a>
        @endcan

        @can('generar_cortes')
            <a href="{{ route('admin.cortes') }}" class="card transition-shadow hover:shadow-card">
                <p class="titulo text-sm text-jade">Cortes</p>
                <p class="mt-2 text-xs leading-relaxed text-texto-suave">
                    Lo cobrado por periodo, desglosado por concepto.
                </p>
            </a>
        @endcan
    </div>

    <div class="card mt-8">
        <p class="titulo text-xs text-texto-suave">Horario de operación</p>
        <p class="mt-2 text-sm">Martes a domingo, 8:30 a 16:00 hrs. <strong>Lunes cerrado.</strong></p>
    </div>
@endsection
