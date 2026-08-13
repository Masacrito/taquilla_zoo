@extends('layouts.interno')

@section('titulo', 'Compra ' . $compra->folio)
@section('subtitulo', $compra->cliente->nombreCompleto())

@section('contenido')

    <a href="{{ route('admin.compras.index') }}" class="mb-4 inline-block text-sm text-jade hover:underline">
        ← Compras
    </a>

    <div class="grid gap-4 lg:grid-cols-3">

        <div class="card lg:col-span-2">
            <p class="titulo text-sm text-jade">Detalle</p>

            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-texto-suave">Estado</dt>
                    <dd class="font-medium">{{ str_replace('_', ' ', $compra->estado) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Fecha de compra</dt>
                    <dd>{{ $compra->fecha_compra->translatedFormat('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Fecha de visita</dt>
                    <dd class="font-medium">{{ $compra->fecha_visita->translatedFormat('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Pases</dt>
                    <dd>{{ $compra->pases_usados }} usados de {{ $compra->pases_total }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">QR emitido</dt>
                    <dd>{{ $compra->qr_token ? 'Sí' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Visitante</dt>
                    <dd>
                        <a href="{{ route('admin.clientes.ver', $compra->cliente) }}"
                           class="text-jade hover:underline">{{ $compra->cliente->correo }}</a>
                    </dd>
                </div>
            </dl>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-borde text-left text-xs text-texto-suave">
                            <th class="py-2">Concepto</th>
                            <th class="py-2 text-center">H / M</th>
                            <th class="py-2 text-center">Cantidad</th>
                            <th class="py-2 text-right">Precio</th>
                            <th class="py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($compra->detalle as $renglon)
                            <tr class="border-b border-borde last:border-0">
                                <td class="py-2">{{ $renglon->rubro_nombre_snap }}</td>
                                <td class="py-2 text-center tabular-nums text-xs">
                                    {{ $renglon->cant_hombre }} / {{ $renglon->cant_mujer }}
                                </td>
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

            <p class="mt-3 text-[11px] text-texto-suave">
                El concepto y el precio son los que estaban vigentes al comprar. Cambiar una tarifa hoy
                no altera este importe.
            </p>
        </div>

        <div class="grid gap-4 lg:col-span-1">

            <div class="card">
                <p class="titulo text-sm text-jade">Pagos</p>

                @forelse ($compra->pagos as $pago)
                    <div class="mt-3 border-b border-borde pb-3 last:border-0 last:pb-0 text-sm">
                        <p class="font-medium">{{ ucfirst($pago->estado) }}</p>
                        <p class="mt-0.5 break-all font-mono text-[11px] text-texto-suave">
                            {{ $pago->referencia_externa }}
                        </p>
                        <p class="mt-1 text-xs text-texto-suave">
                            {{ $pago->proveedor }} · ${{ number_format($pago->monto_centavos / 100, 2) }}
                            @if ($pago->autorizacion)
                                · aut. {{ $pago->autorizacion }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-texto-suave">Sin registros de pago.</p>
                @endforelse
            </div>

            @can('cancelar_compras')
                <div class="card">
                    <p class="titulo text-sm text-cinabrio">Cancelar</p>

                    @if ($compra->puedeTransicionarA(\App\Models\Compra::CANCELADA))
                        <p class="mt-2 text-xs text-texto-suave">
                            El folio se conserva y los {{ $compra->pasesDisponibles() }} pases sin usar
                            vuelven al cupo del día.
                        </p>

                        <form method="POST" action="{{ route('admin.compras.cancelar', $compra) }}"
                              class="mt-3 grid gap-2"
                              onsubmit="return confirm('¿Cancelar la compra {{ $compra->folio }}?');">
                            @csrf @method('PUT')
                            <textarea name="motivo" rows="2" class="input text-xs"
                                      placeholder="Motivo de la cancelación" required minlength="5"></textarea>
                            <button type="submit" class="btn-danger btn-sm">Cancelar compra</button>
                        </form>
                    @else
                        <p class="mt-2 text-xs text-texto-suave">
                            Una compra en estado «{{ str_replace('_', ' ', $compra->estado) }}» no se puede cancelar.
                        </p>
                    @endif
                </div>
            @endcan

        </div>
    </div>
@endsection
