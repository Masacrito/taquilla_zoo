@extends('layouts.publico')

@section('titulo', 'Políticas de compra')

@section('contenido')
    <article class="mx-auto max-w-2xl">
        <h1 class="titulo text-lg">Políticas de entrega, cancelación y reembolso</h1>
        <p class="mt-1 text-xs text-texto-suave">
            Compra de boletos en línea · Zoológico Regional Miguel Álvarez del Toro ·
            Actualizado el {{ \Illuminate\Support\Carbon::parse($politicas['actualizado'])->translatedFormat('j \d\e F \d\e Y') }}
        </p>

        @if ($politicas['en_revision'])
            <p class="mt-4 rounded-card border border-magenta/25 bg-magenta-suave px-4 py-3 text-xs text-magenta">
                Versión preliminar: estas políticas están en revisión y pueden cambiar.
            </p>
        @endif

        @foreach ($politicas['secciones'] as $seccion)
            <section class="card mt-5">
                <h2 class="titulo text-sm text-jade">{{ $seccion['titulo'] }}</h2>

                @foreach ($seccion['parrafos'] as $parrafo)
                    <p class="mt-3 text-sm leading-relaxed text-texto-suave">{{ $parrafo }}</p>
                @endforeach

                @isset($seccion['lista'])
                    <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm leading-relaxed text-texto-suave">
                        @foreach ($seccion['lista'] as $punto)
                            <li>{{ $punto }}</li>
                        @endforeach
                    </ul>
                @endisset
            </section>
        @endforeach

        <p class="mt-5 text-sm text-texto-suave">
            Contacto:
            <a href="mailto:{{ $politicas['correo_contacto'] }}" class="text-jade hover:underline">{{ $politicas['correo_contacto'] }}</a>
        </p>
    </article>
@endsection
