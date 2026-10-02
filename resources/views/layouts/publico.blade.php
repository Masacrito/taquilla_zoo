<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') — ZooMAT</title>

    {{-- Marca el documento antes de que se aplique la hoja de estilos. El CSS
         solo esconde los bloques que aparecen al hacer scroll si esta marca
         existe; sin JS la portada se ve completa y quieta, no en blanco. --}}
    <script>document.documentElement.dataset.js = '';</script>

    @vite('resources/css/app.css')
</head>
<body class="min-h-screen">

    <header class="border-b border-borde bg-superficie">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('portal.inicio') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo_semahn2.png') }}"
                     alt="Humanismo que Transforma — Gobierno de Chiapas" class="h-12 w-auto">
                <span class="titulo hidden text-xs leading-tight text-texto sm:block">
                    Zoológico Regional<br>Miguel Álvarez del Toro
                </span>
            </a>

            <nav class="flex items-center gap-3 text-sm">
                @auth('cliente')
                    <a href="{{ route('compras.index') }}" class="text-texto-suave hover:text-jade">Mis compras</a>
                    <a href="{{ route('compras.crear') }}" class="btn-primary btn-sm">Comprar</a>
                    <form method="POST" action="{{ route('portal.salir') }}">
                        @csrf
                        <button type="submit" class="text-xs text-texto-suave hover:text-cinabrio">Salir</button>
                    </form>
                @else
                    <a href="{{ route('portal.ingresar') }}" class="text-texto-suave hover:text-jade">Ingresar</a>
                    <a href="{{ route('portal.registro') }}" class="btn-primary btn-sm">Crear cuenta</a>
                @endauth
            </nav>
        </div>
    </header>

    {{-- Secciones a todo lo ancho, fuera del contenedor de 5xl. La portada
         la usa para su encabezado y el bloque de los jaguares. --}}
    @yield('ancho_completo')

    <main class="mx-auto max-w-5xl px-4 py-8">
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

    {{-- ── Barra de compra ─────────────────────────────────────────────────
         Aparece al pasar el encabezado y mantiene la acción principal al
         alcance mientras la persona lee. Solo en la portada: en el resto del
         portal ya hay un botón de comprar en la cabecera.

         Empieza fuera de pantalla por CSS y el script la sube; si el JS no
         corre, simplemente no aparece y nadie pierde nada. --}}
    @hasSection('ancho_completo')
        <div class="barra-compra fixed inset-x-0 bottom-0 z-40 border-t border-borde
                    bg-superficie/95 px-4 py-3 shadow-[0_-8px_24px_rgba(0,0,0,0.08)] backdrop-blur"
             data-barra-compra>
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="titulo truncate text-[11px] text-texto-suave">
                        {{ $estado['titulo'] ?? 'Zoológico Miguel Álvarez del Toro' }}
                    </p>
                    <p class="truncate text-xs text-texto-suave">
                        Martes a domingo, 8:30 a 16:00 hrs
                    </p>
                </div>

                @auth('cliente')
                    <a href="{{ route('compras.crear') }}" class="btn-primary shrink-0 px-4 sm:px-6">
                        Comprar boletos
                    </a>
                @else
                    {{-- Sin sesión, /comprar lleva a elegir: con cuenta o como invitado. --}}
                    <a href="{{ route('compras.crear') }}" class="btn-primary shrink-0 px-4 sm:px-6">
                        Comprar boletos
                    </a>
                @endauth
            </div>
        </div>
    @endif

    {{-- Datos tomados del sitio oficial zoomat.chiapas.gob.mx: domicilio,
         conmutador, correo de contacto y el programa de visitas escolares que
         opera el área de Educación Ambiental. --}}
    <footer class="mt-16 border-t border-borde bg-superficie">
        <div class="mx-auto max-w-5xl px-4 py-10">

            <div class="grid gap-8 sm:grid-cols-3">

                <div>
                    <p class="titulo mb-3 text-[11px] text-jade">Antes de tu visita</p>
                    <ul class="space-y-1.5 text-xs leading-relaxed text-texto-suave">
                        <li><strong class="text-texto">Martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.</strong></li>
                        <li>Si no llega el correo con tu comprobante, revisa la carpeta de spam o correo no deseado.</li>
                        <li>Verifica que el tipo de visitante sea el correcto: se valida en el acceso y, de lo contrario, se paga boleto.</li>
                        <li>Tercera edad presenta INAPAM. Estudiante presenta credencial. Niño Pavón gratis hasta 1.20 m de estatura.</li>
                    </ul>
                </div>

                <div>
                    <p class="titulo mb-3 text-[11px] text-jade">Visítanos</p>
                    <address class="space-y-1.5 text-xs not-italic leading-relaxed text-texto-suave">
                        <p>Calzada Cerro Hueco S/N<br>Col. El Zapotal, C.P. 29094<br>Tuxtla Gutiérrez, Chiapas</p>
                        <p>
                            Conmutador
                            <a href="tel:+529615438890" class="text-texto hover:text-jade hover:underline">961 543 88 90</a>
                            ext. 1001
                        </p>
                        <p>
                            <a href="mailto:zoomat@zoomat.chiapas.gob.mx"
                               class="text-texto hover:text-jade hover:underline">zoomat@zoomat.chiapas.gob.mx</a>
                        </p>
                        <p>
                            <a href="https://www.zoomat.chiapas.gob.mx/" target="_blank" rel="noopener"
                               class="text-texto hover:text-jade hover:underline">Sitio oficial del ZooMAT</a>
                        </p>
                    </address>
                </div>

                <div>
                    <p class="titulo mb-3 text-[11px] text-jade">Grupos escolares</p>
                    <div class="space-y-1.5 text-xs leading-relaxed text-texto-suave">
                        <p>
                            Educación Ambiental atiende visitas guiadas de preescolar a universidad,
                            de martes a viernes de 9:30 a 14:30 hrs.
                        </p>
                        <p>
                            El programa incluye charlas, juegos didácticos, cuentos y teatro guiñol.
                            Se solicita con oficio dirigido a
                            <a href="mailto:atencionescolarzoomat@gmail.com"
                               class="text-texto hover:text-jade hover:underline">atencionescolarzoomat@gmail.com</a>.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-borde pt-5 text-[11px] text-texto-suave">
                <span>Secretaría de Medio Ambiente e Historia Natural · Gobierno del Estado de Chiapas</span>
                <span aria-hidden="true">·</span>
                {{-- Crédito obligado por la licencia de las siluetas de los
                     recorridos: game-icons.net las publica bajo CC BY 3.0, que
                     permite uso comercial y modificación a cambio de acreditar
                     a los autores. Si algún día se quitan las siluetas, esta
                     línea se va con ellas. --}}
                <a href="https://game-icons.net/" target="_blank" rel="noopener"
                   class="hover:text-jade hover:underline">Siluetas de fauna: game-icons.net (CC BY 3.0)</a>
                <span aria-hidden="true">·</span>
                {{-- El personal entra por otra puerta y con username, no con
                     correo. Sin este enlace tendrían que saberse la URL. --}}
                <a href="{{ route('login') }}" class="hover:text-jade hover:underline">Acceso para personal</a>
            </div>
        </div>
    </footer>

</body>
</html>
