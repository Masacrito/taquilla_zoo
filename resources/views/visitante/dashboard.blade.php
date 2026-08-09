<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Visitante</title>
</head>
<body style="font-family:system-ui,sans-serif; margin:0;">
    <header style="padding:1rem 2rem; background:#1e3a8a; color:#fff; display:flex; justify-content:space-between; align-items:center;">
        <strong>Panel Visitante</strong>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" style="background:none;border:0;color:#fff;cursor:pointer;">Cerrar sesión</button>
        </form>
    </header>

    <main style="padding:2rem;">
        <h1>Bienvenido, {{ auth()->user()->usuario->nombre }}</h1>
        <p style="color:#6b7280;">Rol: {{ auth()->user()->rol->nombre }}</p>

        @if (session('error'))
            <p style="color:red;">{{ session('error') }}</p>
        @endif

        <p>Esta es tu pantalla principal.</p>
    </main>
</body>
</html>
