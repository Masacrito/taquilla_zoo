@extends('layouts.interno')

@section('titulo', 'Panel administrador')
@section('subtitulo', 'Bienvenido, ' . auth()->user()->usuario->nombre)

@section('contenido')

    {{-- ── Lo que necesita atención ───────────────────────────────────────
         Condicional a propósito: un tablero que siempre enseña las mismas
         tarjetas en verde se vuelve invisible en una semana. Si no hay nada
         que atender, esta franja no existe. --}}
    @if (count($avisos) > 0)
        <div class="mb-8 grid gap-2">
            @foreach ($avisos as $aviso)
                @php
                    [$fondo, $texto] = match ($aviso['tipo']) {
                        'alerta'   => ['bg-cinabrio-suave border-cinabrio/25', 'text-cinabrio'],
                        'atencion' => ['bg-magenta-suave border-magenta/25',   'text-magenta'],
                        default    => ['bg-jade-suave border-jade/25',         'text-jade'],
                    };
                @endphp

                <a href="{{ $aviso['ruta'] }}"
                   class="group flex items-center justify-between gap-4 rounded-card border {{ $fondo }}
                          px-5 py-3.5 transition-opacity hover:opacity-85">
                    <p class="text-sm {{ $texto }}">{{ $aviso['texto'] }}</p>
                    <span class="shrink-0 text-sm {{ $texto }} transition-transform group-hover:translate-x-0.5"
                          aria-hidden="true">&rsaquo;</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ── Hoy ────────────────────────────────────────────────────────────
         «Vendido hoy» y «gente que viene hoy» NO son lo mismo: se puede
         comprar el martes para visitar el sábado. El primero es para caja;
         el segundo, para saber a cuánta gente esperar en la puerta. --}}
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 class="titulo text-sm">Hoy</h2>
        <p class="text-xs text-texto-suave">
            {{ now()->translatedFormat('l d \d\e F') }}
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card">
            <p class="titulo text-[11px] text-texto-suave">Vendido</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-jade">
                ${{ number_format($hoy['vendido_centavos'] / 100, 2) }}
            </p>
            <p class="mt-1 text-xs text-texto-suave">
                {{ $hoy['compras'] }} {{ Str::plural('compra', $hoy['compras']) }}
            </p>
        </div>

        <div class="card">
            <p class="titulo text-[11px] text-texto-suave">Pases vendidos</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($hoy['pases_vendidos']) }}</p>
            <p class="mt-1 text-xs text-texto-suave">comprados hoy</p>
        </div>

        <div class="card">
            <p class="titulo text-[11px] text-texto-suave">Visita programada</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($hoy['pases_esperados']) }}</p>
            <p class="mt-1 text-xs text-texto-suave">personas esperadas hoy</p>
        </div>

        <div class="card">
            <p class="titulo text-[11px] text-texto-suave">Ya entraron</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($hoy['ya_entraron']) }}</p>
            @php
                $faltan = max(0, $hoy['pases_esperados'] - $hoy['ya_entraron']);
            @endphp
            <p class="mt-1 text-xs text-texto-suave">
                @if ($hoy['pases_esperados'] === 0)
                    sin visitas programadas
                @else
                    faltan {{ number_format($faltan) }} por llegar
                @endif
            </p>
        </div>
    </div>

    {{-- ── Últimos siete días ─────────────────────────────────────────────
         Barras en CSS puro. El brief no permite librerías de gráficas, y
         para siete barras tampoco hacen falta. El dato sale de `por_dia`,
         que el corte ya calcula: no hay consulta extra. --}}
    @can('ver_estadisticas')
        <div class="mt-10">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 class="titulo text-sm">Últimos 7 días</h2>
                <p class="text-xs text-texto-suave">
                    Total ${{ number_format($semana['total'] / 100, 2) }}
                </p>
            </div>

            <div class="card">
                @if ($semana['total'] === 0)
                    <p class="py-6 text-center text-sm text-texto-suave">
                        No hubo ventas en los últimos siete días.
                    </p>
                @else
                    <div class="flex h-40 items-end justify-between gap-2">
                        @foreach ($semana['dias'] as $dia)
                            <div class="group flex flex-1 flex-col items-center justify-end gap-2">
                                <p class="text-[10px] tabular-nums text-texto-suave opacity-0
                                          transition-opacity group-hover:opacity-100">
                                    ${{ number_format($dia['centavos'] / 100) }}
                                </p>

                                {{-- `min-height` para que un día con venta
                                     pequeña siga siendo visible y no se
                                     confunda con uno sin ventas. --}}
                                <div class="w-full rounded-t-[6px] bg-jade transition-colors
                                            group-hover:bg-jade/80"
                                     style="height: {{ $dia['porcentaje'] }}%;
                                            min-height: {{ $dia['centavos'] > 0 ? '4px' : '0' }};"></div>

                                <p class="text-[10px] text-texto-suave">
                                    {{ $dia['fecha']->translatedFormat('D') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endcan

    {{-- ── Accesos a los módulos ──────────────────────────────────────────
         Cada tarjeta aparece solo si la cuenta tiene el permiso. --}}
    <h2 class="titulo mb-3 mt-10 text-sm">Módulos</h2>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['gestion_usuarios',       'admin.users.index',    'Usuarios',    'Altas, roles, permisos y estado de las cuentas internas.'],
            ['gestion_rubros',         'admin.rubros.index',   'Rubros',      'Las tarifas que se cobran y su vigencia.'],
            ['gestion_catalogos',      'admin.catalogos.index','Catálogos',   'Países, estados, municipios y clasificación del visitante.'],
            ['gestion_aforo',          'admin.aforo.index',    'Calendario',  'Qué días abre el zoológico y cuáles se cierran.'],
            ['gestion_clientes',       'admin.compras.index',  'Compras',     'Folios vendidos, su estado y cancelaciones.'],
            ['gestion_clientes',       'admin.clientes.index', 'Visitantes',  'Quién se registró en el portal.'],
            ['generar_cortes',         'admin.cortes',         'Cortes',      'Lo cobrado por periodo, desglosado por concepto.'],
            ['ver_estadisticas',       'admin.estadisticas',   'Estadísticas','Quién visita el zoológico y de dónde viene.'],
            ['validar_accesos',        'accesos.escanear',     'Acceso',      'Lectura de códigos QR en el torniquete.'],
            ['ver_bitacora_accesos',   'accesos.bitacora',     'Entradas',    'Todo escaneo registrado, aceptado o rechazado.'],
            ['ver_bitacora_auditoria', 'admin.bitacora.index', 'Bitácora',    'Quién cambió qué y cuándo.'],
            ['ver_errores',            'admin.errores.index',  'Fallos',      'Qué se rompió, cuántas veces y desde cuándo.'],
        ] as [$permiso, $ruta, $nombre, $descripcion])
            @can($permiso)
                <a href="{{ route($ruta) }}" class="card transition-shadow hover:shadow-card">
                    <p class="titulo text-sm text-jade">{{ $nombre }}</p>
                    <p class="mt-2 text-xs leading-relaxed text-texto-suave">{{ $descripcion }}</p>
                </a>
            @endcan
        @endforeach
    </div>
@endsection
