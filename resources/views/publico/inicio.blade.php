@extends('layouts.publico')

@section('titulo', 'Venta de boletos en línea')

@section('contenido')
    <section class="text-center">
        <h1 class="titulo text-2xl leading-tight text-texto sm:text-3xl">
            Zoológico Regional<br>Miguel Álvarez del Toro
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-sm text-texto-suave">
            Compra tus boletos en línea, recibe tu código QR por correo y preséntalo en el acceso.
            Sin filas.
        </p>

        <div class="mt-6 flex justify-center gap-3">
            @auth('cliente')
                <a href="{{ route('compras.crear') }}" class="btn-primary">Comprar boletos</a>
                <a href="{{ route('compras.index') }}" class="btn-outline">Mis compras</a>
            @else
                <a href="{{ route('portal.registro') }}" class="btn-primary">Crear cuenta</a>
                <a href="{{ route('portal.ingresar') }}" class="btn-outline">Ya tengo cuenta</a>
            @endauth
        </div>
    </section>

    @if ($rubros->isNotEmpty())
        <section class="mt-14">
            <h2 class="titulo mb-4 text-sm text-jade">Tarifas vigentes</h2>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rubros as $rubro)
                    <div class="card">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="font-medium">{{ $rubro->tipo }}</p>
                            <p class="shrink-0 text-lg font-semibold tabular-nums">
                                {{ $rubro->esGratis() ? 'Gratis' : $rubro->precioFormateado() }}
                            </p>
                        </div>
                        @if ($rubro->descripcion)
                            <p class="mt-2 text-xs text-texto-suave">{{ $rubro->descripcion }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <section class="mt-14">
            <div class="card text-center">
                <p class="text-sm text-texto-suave">
                    Todavía no hay tarifas publicadas. Vuelve pronto.
                </p>
            </div>
        </section>
    @endif
@endsection
