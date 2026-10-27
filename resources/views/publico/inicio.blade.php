@extends('layouts.experiencia')
@section('experiencia')
<main id="recorrido" class="zoo-journey" data-sculpture-journey data-model="{{ asset('models/huella/huella.glb') }}" data-draco="{{ asset('vendor/draco/') }}/">
    <div class="zoo-stage" data-stage>
        {{-- La imagen va por asset() y no en el CSS: con /taquilla, una url('/images/...') apuntaría a la raíz del dominio. --}}
        <div class="zoo-atmosphere" style="--atmosfera: url('{{ asset('images/jaguar-moteado.webp') }}')" aria-hidden="true"></div>
        <img class="zoo-poster" data-poster src="{{ asset('models/huella/poster.webp') }}" width="1200" height="1200" fetchpriority="high" alt="Huella escultórica de jaguar con cinco almohadillas separables de jade y piedra arena.">
        <div class="zoo-ground-shadow" data-shadow aria-hidden="true"></div>
        <canvas class="zoo-canvas" data-canvas aria-label="Huella interactiva: arrastra para girarla o utiliza los controles de la escena"></canvas>
        <div class="zoo-stage-tools" data-scene-tools hidden>
            <p class="zoo-gesture">Acércate con el cursor · arrastra para girar</p>
            <div class="zoo-controls" role="group" aria-label="Controles de la huella">
                <button type="button" data-explode aria-pressed="false">Explorar <span aria-hidden="true">＋</span></button>
                <button type="button" data-reset aria-label="Restablecer giro y reunir la huella">Restablecer</button>
                <button type="button" data-pause aria-pressed="false">Pausar</button>
            </div>
        </div>
        <p class="zoo-scene-status" data-scene-status role="status" aria-live="polite"></p>
        <span class="zoo-specimen" aria-hidden="true">HUELLA DE JAGUAR <i></i> CHIAPAS, MÉXICO</span>
    </div>
    <nav class="zoo-chapters" aria-label="Pasos para comprar">
        @foreach (['encuentro' => 'Inicio', 'visitantes' => 'Visitantes', 'origen' => 'Procedencia', 'fecha' => 'Fecha', 'pago' => 'Pago', 'acceso' => 'Tu QR'] as $id => $label)
            <a href="#{{ $id }}" aria-label="{{ $label }}"><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><i></i></a>
        @endforeach
    </nav>
    <section id="encuentro" class="zoo-chapter zoo-intro" data-chapter>
        <div class="zoo-copy">
            <p class="zoo-eyebrow"><span class="zoo-live-dot"></span>{{ $estado['titulo'] }} @if($estado['detalle'])<span class="zoo-status-detail">· {{ $estado['detalle'] }}</span>@endif</p>
            <h1>Tu próximo<br>encuentro.<br><em>Más cerca.</em></h1>
            <p class="zoo-lead">Compra tu acceso desde casa<br>y entra directo con tu QR.</p>
            <a class="zoo-button" href="{{ route('compras.crear') }}">Comprar boletos <span aria-hidden="true">↗</span></a>
            <a class="zoo-scroll-link" href="#visitantes">Así empieza tu visita <span aria-hidden="true">↓</span></a>
        </div>
        <div class="zoo-intro-bottom"><span>Una mirada a lo que te espera.</span><span>Desliza para descubrir <b aria-hidden="true">↓</b></span></div>
    </section>
    <section id="visitantes" class="zoo-chapter zoo-copy-right" data-chapter>
        <div class="zoo-copy">
            <p class="zoo-eyebrow">01 / Cada paso se comparte</p>
            <h2>Un encuentro.<br><em>Para todos.</em></h2>
            <p class="zoo-lead">Elige tu tipo de visitante.</p>
            <p>{{ $rubros->isNotEmpty() ? $rubros->pluck('tipo')->unique()->implode(' · ').'.' : 'Acceso General · Nacional o Extranjero · Niños · Tercera edad.' }}</p>
            <details class="zoo-rates"><summary>Consultar tarifas vigentes <span aria-hidden="true">＋</span></summary>
                <dl>@forelse($rubros as $rubro)<div><dt>{{ $rubro->tipo }}</dt><dd>{{ $rubro->esGratis() ? 'Gratis' : $rubro->precioFormateado() }}</dd></div>@empty<div><dt>Consulta la disponibilidad al comprar.</dt></div>@endforelse</dl>
            </details>
            <p class="zoo-note">Tu tipo de visitante se valida en la entrada: Extranjero, Nacional, Niños e INAPAM para tercera edad.</p>
            <a class="zoo-text-link" href="{{ route('compras.crear') }}">Elegir mis boletos <span aria-hidden="true">↗</span></a>
        </div>
    </section>
    <section id="origen" class="zoo-chapter" data-chapter>
        <div class="zoo-copy">
            <p class="zoo-eyebrow">02 / Todos los caminos llegan aquí</p>
            <h2>Desde donde estés.<br><em>¿De dónde vienes?</em></h2>
            <p class="zoo-lead">Cuéntanos desde dónde nos visitas.</p>
            <p>Selecciona tu estado, tu municipio de Chiapas o tu país de origen durante la compra.</p>
            <div class="zoo-location-line"><span>Tu lugar de origen</span><i aria-hidden="true"></i><strong>ZooMAT</strong></div>
            <p class="zoo-note">Unos datos, un destino. El Zapotal, Tuxtla Gutiérrez.</p>
        </div>
    </section>
    <section id="fecha" class="zoo-chapter zoo-copy-right" data-chapter>
        <div class="zoo-copy">
            <p class="zoo-eyebrow">03 / Hazle un espacio a la naturaleza</p>
            <h2>Hay días<br><em>que se sienten vivos.</em></h2>
            <p class="zoo-lead">Programa tu fecha de visita.</p>
            <div class="zoo-schedule"><strong>8:30 — 16:00</strong><span>Martes a domingo</span></div>
            <p class="zoo-note">Martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado. El calendario de compra muestra las fechas disponibles.</p>
            <a class="zoo-button" href="{{ route('compras.crear') }}">Comprar boletos <span aria-hidden="true">↗</span></a>
        </div>
    </section>
    <section id="pago" class="zoo-chapter" data-chapter>
        <div class="zoo-copy">
            <p class="zoo-eyebrow">04 / Todo empieza desde casa</p>
            <h2>Unos pasos.<br><em>Todo por descubrir.</em></h2>
            <p class="zoo-lead">Paga en línea y recibe<br>tu QR por correo.</p>
            <p>Al crear tu cuenta, verifica tu correo con el código que te enviamos. Revisa también spam.</p>
            <p class="zoo-note">Cuando tu pago esté confirmado, tendrás tu acceso listo. Si no llega el correo, tu QR vigente está en <a href="{{ auth('cliente')->check() ? route('compras.index') : route('portal.ingresar') }}">Mis compras</a>.</p>
            <a class="zoo-text-link" href="{{ route('compras.crear') }}">Empezar mi compra <span aria-hidden="true">↗</span></a>
        </div>
    </section>
    <section id="acceso" class="zoo-chapter zoo-copy-right zoo-finale" data-chapter>
        <div class="zoo-copy">
            <p class="zoo-eyebrow">05 / Nos vemos en ZooMAT</p>
            <h2>De tu pantalla.<br><em>A su mundo.</em></h2>
            <p class="zoo-lead">Tu acceso, en un QR.</p>
            <div class="zoo-ticket"><span class="zoo-qr-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3z"/><path d="M5 5h2v2H5zM17 5h2v2h-2zM5 17h2v2H5z" fill="currentColor" stroke="none"/><path d="M12 3v3m0 3v4H3m9 3h4v5m-4 0v-3m7-6h2v4h-3v5h3m-5-9v3"/></svg></span><div><strong>Pagas → recibes QR → entras</strong><p>Muéstralo en el torniquete.<br>El QR se invalida al pasar.</p></div></div>
            <a class="zoo-button" href="{{ route('compras.crear') }}">Comprar boletos <span aria-hidden="true">↗</span></a>
            <a class="zoo-text-link" href="{{ auth('cliente')->check() ? route('compras.index') : route('portal.ingresar') }}">Ya compré: ver mi QR</a>
        </div>
    </section>
</main>
@include('publico.areas')
@endsection
