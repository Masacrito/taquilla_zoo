@extends('layouts.publico')

@section('titulo', 'Compra ' . $compra->folio)

@section('contenido')
    <div class="mx-auto max-w-2xl">

        <a href="{{ route('compras.index') }}" class="mb-4 inline-block text-sm text-jade hover:underline">
            ← Mis compras
        </a>

        {{-- ═══ QR ═══
             Es lo que el visitante abre parado en la puerta, con el sol de
             Tuxtla encima: el código va grande, sobre blanco y sin nada que
             compita. Todo lo demás baja. --}}
        @if ($compra->estaPagada() && filled($compra->qr_token))
            <div class="card mb-6 text-center">
                <p class="titulo text-sm text-jade">Tu pase de entrada</p>

                <div class="mx-auto my-5 w-fit rounded-card bg-white p-4">
                    <img src="{{ route('compras.qr', $compra->folio) }}"
                         alt="Código QR de la compra {{ $compra->folio }}"
                         class="h-64 w-64">
                </div>

                <p class="text-sm font-medium tabular-nums">{{ $compra->folio }}</p>

                <p class="mx-auto mt-2 max-w-xs text-sm text-texto-suave">
                    Válido el
                    <strong class="text-texto">{{ $compra->fecha_visita->translatedFormat('l d \d\e F') }}</strong>,
                    de 8:30 a 16:00 hrs.
                </p>

                @if ($compra->pases_usados > 0)
                    <p class="mt-4 inline-block rounded-badge bg-arena/25 px-3 py-1.5 text-xs text-texto-suave">
                        Ya entraron {{ $compra->pases_usados }} de {{ $compra->pases_total }} pases
                    </p>
                @endif

                <p class="mt-5 text-xs text-texto-suave">
                    Súbele el brillo a tu pantalla antes de llegar al acceso.
                </p>
            </div>
        @elseif ($compra->estado === \App\Models\Compra::PENDIENTE_PAGO)
            <div class="card mb-6 text-center">
                <p class="titulo text-sm">Pendiente de pago</p>
                <p class="mt-3 text-sm text-texto-suave">
                    El código QR se emite cuando el banco confirma el pago.
                </p>
            </div>
        @endif

        {{-- ═══ Detalle ═══ --}}
        <div class="card">
            <p class="titulo text-sm text-jade">Detalle de la compra</p>

            <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between sm:block">
                    <dt class="text-texto-suave">Folio</dt>
                    <dd class="font-medium">{{ $compra->folio }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-texto-suave">Estado</dt>
                    <dd class="font-medium">{{ str_replace('_', ' ', $compra->estado) }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-texto-suave">Fecha de compra</dt>
                    <dd>{{ $compra->fecha_compra->translatedFormat('d/m/Y H:i') }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-texto-suave">Fecha de visita</dt>
                    <dd>{{ $compra->fecha_visita->translatedFormat('d/m/Y') }}</dd>
                </div>
            </dl>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-borde text-left text-xs text-texto-suave">
                            <th class="py-2">Concepto</th>
                            <th class="py-2 text-center">Cantidad</th>
                            <th class="py-2 text-right">Precio</th>
                            <th class="py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($compra->detalle as $renglon)
                            <tr class="border-b border-borde last:border-0">
                                {{-- Nombre y precio congelados al momento de comprar --}}
                                <td class="py-2">{{ $renglon->rubro_nombre_snap }}</td>
                                <td class="py-2 text-center tabular-nums">{{ $renglon->cantidad }}</td>
                                <td class="py-2 text-right tabular-nums">
                                    ${{ number_format($renglon->precio_centavos_snap / 100, 2) }}
                                </td>
                                <td class="py-2 text-right tabular-nums">{{ $renglon->importeFormateado() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-baseline justify-between border-t border-borde pt-4">
                <span class="titulo text-sm">Total</span>
                <span class="text-xl font-semibold tabular-nums">{{ $compra->totalFormateado() }}</span>
            </div>
        </div>

        <p class="mt-4 text-xs text-texto-suave">
            Verifica que el tipo de visitante sea el correcto: se valida en el acceso y, de lo
            contrario, se paga boleto.
        </p>

        {{-- Cómo llegar. Aparece aquí y no en la pantalla de compra porque lo
             busca quien ya tiene el boleto y va en camino. --}}
        <div class="card mt-6">
            <p class="titulo text-sm text-jade">Cómo llegar</p>
            <address class="mt-3 text-sm not-italic leading-relaxed text-texto-suave">
                Calzada Cerro Hueco S/N, Col. El Zapotal<br>
                Tuxtla Gutiérrez, Chiapas, C.P. 29094
            </address>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="https://www.google.com/maps/search/?api=1&query=Zool%C3%B3gico+Miguel+%C3%81lvarez+del+Toro+Tuxtla+Guti%C3%A9rrez"
                   target="_blank" rel="noopener" class="btn-outline btn-sm">Abrir en el mapa</a>
                <a href="tel:+529615438890" class="btn-outline btn-sm">961 543 88 90</a>
            </div>
        </div>
    </div>
@endsection
