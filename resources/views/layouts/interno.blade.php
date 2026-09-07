@php
    $cuenta = auth()->user();
    $rol    = $cuenta->rol->nombre;
    $inicio = $cuenta->isAdmin() ? route('admin.dashboard') : route('taquilla.dashboard');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') — ZooMAT</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen md:flex">

    {{-- ═══ SIDEBAR ═══ --}}
    <aside class="bg-texto text-white md:min-h-screen md:w-64 md:shrink-0">
        <div class="px-5 py-5">
            <p class="titulo text-xl leading-none">ZooMAT</p>
            <p class="mt-1.5 text-[11px] text-white/50">Sistema de taquilla</p>
        </div>

        <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:mt-4 md:flex-col md:overflow-visible">
            <a href="{{ $inicio }}"
               class="shrink-0 rounded-[10px] px-4 py-2.5 text-sm transition-colors
                      {{ request()->routeIs('*.dashboard') ? 'bg-jade font-semibold text-white' : 'text-white/70 hover:bg-white/10' }}">
                Inicio
            </a>

            @php
                $enlaces = [
                    ['ruta' => 'admin.users.index',     'patron' => 'admin.users.*',     'texto' => 'Usuarios',  'permisos' => ['gestion_usuarios', 'crear_usuarios', 'editar_usuarios']],
                    ['ruta' => 'admin.rubros.index',    'patron' => 'admin.rubros.*',    'texto' => 'Rubros',    'permisos' => ['gestion_rubros', 'editar_rubros']],
                    ['ruta' => 'admin.catalogos.index', 'patron' => 'admin.catalogos.*', 'texto' => 'Catálogos', 'permisos' => ['gestion_catalogos', 'editar_catalogos']],
                    ['ruta' => 'admin.aforo.index',     'patron' => 'admin.aforo.*',     'texto' => 'Calendario','permisos' => ['gestion_aforo']],
                    ['ruta' => 'admin.compras.index',   'patron' => 'admin.compras.*',   'texto' => 'Compras',   'permisos' => ['gestion_clientes']],
                    ['ruta' => 'admin.clientes.index',  'patron' => 'admin.clientes.*',  'texto' => 'Visitantes','permisos' => ['gestion_clientes']],
                    ['ruta' => 'admin.cortes',          'patron' => 'admin.cortes',      'texto' => 'Cortes',    'permisos' => ['generar_cortes']],
                    ['ruta' => 'admin.estadisticas',    'patron' => 'admin.estadisticas','texto' => 'Estadísticas','permisos' => ['ver_estadisticas']],
                    ['ruta' => 'accesos.escanear',      'patron' => 'accesos.escanear',  'texto' => 'Acceso',    'permisos' => ['validar_accesos']],
                    ['ruta' => 'accesos.bitacora',      'patron' => 'accesos.bitacora',  'texto' => 'Entradas',  'permisos' => ['ver_bitacora_accesos']],
                    ['ruta' => 'admin.bitacora.index',  'patron' => 'admin.bitacora.*',  'texto' => 'Bitácora',  'permisos' => ['ver_bitacora_auditoria']],
                ];
            @endphp

            @foreach ($enlaces as $enlace)
                @canany($enlace['permisos'])
                    <a href="{{ route($enlace['ruta']) }}"
                       class="shrink-0 rounded-[10px] px-4 py-2.5 text-sm transition-colors
                              {{ request()->routeIs($enlace['patron']) ? 'bg-jade font-semibold text-white' : 'text-white/70 hover:bg-white/10' }}">
                        {{ $enlace['texto'] }}
                    </a>
                @endcanany
            @endforeach
        </nav>

        {{-- Cuenta activa --}}
        <div class="mt-auto hidden border-t border-white/10 px-5 py-4 md:block">
            <p class="truncate text-sm font-medium">{{ $cuenta->usuario->nombre }}</p>
            <p class="text-xs text-white/50">{{ $rol }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="text-xs text-white/70 underline-offset-2 hover:text-white hover:underline">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- ═══ CONTENIDO ═══ --}}
    <div class="flex-1">
        <header class="flex items-center justify-between gap-4 border-b border-borde bg-superficie px-6 py-4">
            <div>
                <h1 class="titulo text-base text-texto">@yield('titulo')</h1>
                @hasSection('subtitulo')
                    <p class="mt-0.5 text-xs text-texto-suave">@yield('subtitulo')</p>
                @endif
            </div>

            <div class="flex items-center gap-5">
                <img src="{{ asset('images/logo_semahn.png') }}"
                     alt="Secretaría de Medio Ambiente e Historia Natural — Gobierno de Chiapas"
                     class="hidden h-11 w-auto sm:block">
                <form method="POST" action="{{ route('logout') }}" class="md:hidden">
                    @csrf
                    <button type="submit" class="text-xs text-texto-suave hover:text-cinabrio">Salir</button>
                </form>
            </div>
        </header>

        <main class="p-6">
            @if (session('success'))
                <div class="flash-ok">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="flash-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="flash-error">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('contenido')
        </main>
    </div>

</body>
</html>
