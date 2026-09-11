{{--
    Plantilla de las pantallas de error.

    NO consulta la base, y a propósito: un error 500 puede ser justamente la
    base caída, y una pantalla de error que necesita la base no se puede
    pintar. Por eso tampoco lleva @auth ni el encabezado de sesión —con
    SESSION_DRIVER=database, preguntar por la sesión es una consulta.

    Solo enlaces estáticos y la identidad institucional.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') — ZooMAT</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-fondo">

    <div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-4 py-12 text-center">

        <img src="{{ asset('images/logo_semahn2.png') }}"
             alt="Gobierno del Estado de Chiapas" class="mb-8 h-14 w-auto">

        <p class="titulo text-5xl text-jade sm:text-6xl">@yield('codigo')</p>

        <h1 class="titulo mt-4 text-lg text-texto sm:text-xl">@yield('titulo')</h1>

        <p class="mt-4 max-w-sm text-sm leading-relaxed text-texto-suave">
            @yield('mensaje')
        </p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @yield('acciones')
        </div>

        @hasSection('nota')
            <p class="mt-8 max-w-sm text-xs leading-relaxed text-texto-suave">
                @yield('nota')
            </p>
        @endif

        <p class="mt-12 text-[11px] text-texto-suave">
            Zoológico Regional Miguel Álvarez del Toro<br>
            Secretaría de Medio Ambiente e Historia Natural
        </p>
    </div>

</body>
</html>
