@extends('layouts.publico')

@section('titulo', 'Mis compras')

@section('contenido')
    <h1 class="titulo mb-6 text-lg">Mis compras</h1>

    @if ($compras->isEmpty())
        <div class="card text-center">
            <p class="text-sm text-texto-suave">Todavía no has comprado boletos.</p>
            <a href="{{ route('compras.crear') }}" class="btn-primary mt-4">Comprar ahora</a>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($compras as $compra)
                <a href="{{ route('compras.ver', $compra->folio) }}"
                   class="card flex flex-wrap items-center justify-between gap-4 transition-shadow hover:shadow-card">
                    <div>
                        <p class="font-medium">{{ $compra->folio }}</p>
                        <p class="mt-0.5 text-xs text-texto-suave">
                            Visita: {{ $compra->fecha_visita->translatedFormat('d/m/Y') }}
                            · {{ $compra->pases_total }} {{ Str::plural('pase', $compra->pases_total) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="text-sm font-semibold tabular-nums">{{ $compra->totalFormateado() }}</span>
                        @php
                            $clase = match (true) {
                                $compra->estaPagada() => 'badge-activo',
                                $compra->estado === \App\Models\Compra::PENDIENTE_PAGO => 'badge-especial',
                                default => 'badge-inactivo',
                            };
                        @endphp
                        <span class="{{ $clase }}">{{ str_replace('_', ' ', $compra->estado) }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $compras->links() }}</div>
    @endif
@endsection
