@extends('layouts.publico')

@section('titulo', 'Mis compras')

@section('contenido')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="titulo text-lg">Mis compras</h1>
            <p class="mt-1 text-sm text-texto-suave">
                Aquí vive tu código QR. No hace falta imprimirlo: basta con mostrarlo en el acceso.
            </p>
        </div>
        <a href="{{ route('compras.crear') }}" class="btn-primary btn-sm">Comprar más</a>
    </div>

    @if ($compras->isEmpty())
        <div class="card py-12 text-center">
            <p class="titulo text-sm text-texto-suave">Todavía no has comprado boletos</p>
            <p class="mx-auto mt-3 max-w-sm text-sm text-texto-suave">
                Elige el día en el calendario, paga en línea y tu código QR llega por correo.
                Sin filas en taquilla.
            </p>
            <a href="{{ route('compras.crear') }}" class="btn-primary mt-6">Comprar boletos</a>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($compras as $compra)
                @php
                    // El estado manda el color y, sobre todo, el texto: «acceso
                    // parcial» no le dice nada a un visitante, «te quedan 2 de 4
                    // pases» sí.
                    [$clase, $leyenda] = match (true) {
                        $compra->estado === \App\Models\Compra::PENDIENTE_PAGO
                            => ['badge-especial', 'Esperando confirmación del pago'],
                        $compra->estado === \App\Models\Compra::ACCESO_PARCIAL
                            => ['badge-activo', "Te quedan {$compra->pasesDisponibles()} de {$compra->pases_total} pases"],
                        $compra->estaPagada()
                            => ['badge-activo', 'Tu código QR está listo'],
                        $compra->estado === \App\Models\Compra::UTILIZADA
                            => ['badge-inactivo', 'Ya usaste todos los pases'],
                        default
                            => ['badge-inactivo', null],
                    };
                @endphp

                <a href="{{ route('compras.ver', $compra->folio) }}"
                   class="card group flex flex-wrap items-center justify-between gap-4
                          transition-shadow hover:shadow-card">
                    <div class="min-w-48 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium tabular-nums">{{ $compra->folio }}</p>
                            <span class="{{ $clase }}">{{ str_replace('_', ' ', $compra->estado) }}</span>
                        </div>

                        <p class="mt-1.5 text-sm text-texto-suave">
                            {{ ucfirst($compra->fecha_visita->translatedFormat('l d \d\e F \d\e Y')) }}
                        </p>

                        <p class="mt-0.5 text-xs text-texto-suave">
                            {{ $compra->pases_total }} {{ Str::plural('pase', $compra->pases_total) }}
                            @if ($leyenda)
                                · {{ $leyenda }}
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="text-sm font-semibold tabular-nums">{{ $compra->totalFormateado() }}</span>
                        <span class="text-jade transition-transform group-hover:translate-x-0.5"
                              aria-hidden="true">&rsaquo;</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $compras->links() }}</div>
    @endif
@endsection
