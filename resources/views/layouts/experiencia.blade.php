<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Compra tu acceso al ZooMAT desde casa, recibe tu QR por correo y preséntalo en el torniquete.">
    <title>Compra tu acceso desde casa — ZooMAT</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/css/huella.css', 'resources/js/huella.js'])
</head>
<body class="zoo-experience">
    <a href="#recorrido" class="zoo-skip">Saltar al contenido</a>
    <header class="zoo-header">
        <a class="zoo-brand" href="{{ route('portal.inicio') }}" aria-label="ZooMAT, inicio">
            <img src="{{ asset('images/logo_semahn2.png') }}" width="160" height="54" alt="Humanismo que Transforma — Gobierno de Chiapas">
            <span>Zoológico Regional<br><strong>Miguel Álvarez del Toro</strong></span>
        </a>
        <nav aria-label="Navegación principal">
            <a href="#areas">Áreas</a>
            <a href="{{ auth('cliente')->check() ? route('compras.index') : route('portal.ingresar') }}">Mis compras</a>
            <a class="zoo-button zoo-button-small" href="{{ route('compras.crear') }}">Comprar boletos <span aria-hidden="true">↗</span></a>
        </nav>
    </header>
    @yield('experiencia')
    <footer class="zoo-footer">
        <div><strong>ZooMAT · El Zapotal</strong><p>Calzada Cerro Hueco S/N · Tuxtla Gutiérrez, Chiapas</p><a href="tel:+529615438890">961 543 88 90</a> · <a href="mailto:zoomat@zoomat.chiapas.gob.mx">zoomat@zoomat.chiapas.gob.mx</a></div>
        <div><span>Secretaría de Medio Ambiente e Historia Natural<br>Gobierno del Estado de Chiapas</span><p><a href="{{ route('portal.politicas') }}">Políticas de compra</a> · <a href="{{ route('login') }}">Acceso para personal</a></p><a href="mailto:atencionescolarzoomat@gmail.com">Visitas escolares</a></div>
        <div><a href="https://game-icons.net/" target="_blank" rel="noopener">Siluetas de fauna: game-icons.net (CC BY 3.0)</a></div>
    </footer>
</body>
</html>
