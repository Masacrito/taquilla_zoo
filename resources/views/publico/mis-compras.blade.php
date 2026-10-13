@extends('layouts.publico')

@section('titulo', $archivadas ? 'Compras archivadas' : 'Mis compras')

@section('contenido')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="titulo text-lg">{{ $archivadas ? 'Compras archivadas' : 'Mis compras' }}</h1>
            <p class="mt-1 text-sm text-texto-suave">
                @if ($archivadas)
                    Lo que sacaste de tu lista y las compras que expiraron sin pagarse.
                @else
                    Aquí vive tu código QR. No hace falta imprimirlo: basta con mostrarlo en el acceso.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if ($archivadas)
                <a href="{{ route('compras.index') }}" class="text-sm text-jade hover:underline">← Mis compras</a>
            @else
                <a href="{{ route('compras.index', ['archivadas' => 1]) }}"
                   class="text-sm text-jade hover:underline">Ver archivadas</a>
            @endif
            <a href="{{ route('compras.crear') }}" class="btn-primary btn-sm">Comprar más</a>
        </div>
    </div>

    @if ($compras->isEmpty())
        <div class="card py-12 text-center">
            @if ($archivadas)
                <p class="titulo text-sm text-texto-suave">No tienes compras archivadas</p>
            @else
                <p class="titulo text-sm text-texto-suave">No tienes compras a la vista</p>
                <p class="mx-auto mt-3 max-w-sm text-sm text-texto-suave">
                    Elige el día en el calendario, paga en línea y tu código QR llega por correo.
                    Sin filas en taquilla.
                </p>
                <a href="{{ route('compras.crear') }}" class="btn-primary mt-6">Comprar boletos</a>
            @endif
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($compras as $compra)
                @php
                    // El estado manda el color y, sobre todo, el texto: «acceso
                    // parcial» no le dice nada a un visitante, «te quedan 2 de 4
                    // pases» sí.
                    [$clase, $leyenda] = match (true) {
                        $compra->estaPendiente()
                            => ['badge-especial', 'Aún no se paga'],
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

                {{-- La tarjeta ya no es un enlace completo: lleva botones
                     dentro, y un formulario no puede vivir dentro de un <a>. --}}
                <div class="card flex flex-wrap items-center justify-between gap-4">
                    <a href="{{ route('compras.ver', $compra->folio) }}" class="group min-w-48 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium tabular-nums group-hover:text-jade">{{ $compra->folio }}</p>
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
                    </a>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="mr-2 text-sm font-semibold tabular-nums">{{ $compra->totalFormateado() }}</span>

                        @if ($compra->estaPendiente())
                            <form method="POST" action="{{ $compra->urlPagar() }}">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm">
                                    <svg class="mr-1 inline h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
                                    </svg>Pagar
                                </button>
                            </form>
                        @endif

                        @if ($compra->estaArchivada())
                            <form method="POST" action="{{ route('compras.desarchivar', $compra->folio) }}">
                                @csrf
                                <button type="submit" class="btn-outline btn-sm">Desarchivar</button>
                            </form>
                        @elseif ($compra->esArchivable())
                            <form method="POST" action="{{ route('compras.archivar', $compra->folio) }}">
                                @csrf
                                <button type="submit" class="btn-outline btn-sm">
                                    <svg class="mr-1 inline h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4"/>
                                    </svg>Archivar
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $compras->links() }}</div>
    @endif
@endsection
