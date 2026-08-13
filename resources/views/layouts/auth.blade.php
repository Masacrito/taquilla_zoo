<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Acceso') — ZooMAT</title>
    @vite('resources/css/app.css')
</head>
<body class="grid min-h-screen place-items-center p-4">
    <main class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <img src="{{ asset('images/logo_semahn2.png') }}"
                 alt="Humanismo que Transforma — Gobierno de Chiapas"
                 class="mx-auto mb-6 h-20 w-auto">
            <h1 class="titulo text-base leading-snug text-texto">Zoológico Regional<br>Miguel Álvarez del Toro</h1>
            {{-- Distinguir claramente esta pantalla de la del visitante: la
                 marca es la misma, pero aquí se entra con username. --}}
            <p class="mt-1.5 text-xs font-medium text-jade">Acceso para personal</p>
        </div>

        @yield('contenido')
    </main>
</body>
</html>
