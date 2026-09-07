@extends('layouts.interno')

@section('titulo', 'Corte de ingresos')
@section('subtitulo', 'Dinero cobrado por venta en línea')

@php
    $pesos = fn (int $centavos) => '$' . number_format($centavos / 100, 2);
@endphp

@section('contenido')

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Desde</label>
            <input type="date" name="desde" class="input" value="{{ $corte['desde'] }}">
        </div>
        <div>
            <label class="label">Hasta</label>
            <input type="date" name="hasta" class="input" value="{{ $corte['hasta'] }}">
        </div>
        <div>
            <label class="label">Agrupar por</label>
            <select name="base" class="input min-w-44">
                <option value="compra" @selected($corte['base'] === 'compra')>Fecha de compra</option>
                <option value="visita" @selected($corte['base'] === 'visita')>Fecha de visita</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">Generar</button>
        <button type="button" onclick="window.print()" class="btn-outline">Imprimir</button>
    </form>

    {{-- ═══ Resumen ═══ --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Total cobrado</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums text-jade">
                {{ $pesos($corte['resumen']['total_centavos']) }}
            </p>
        </div>
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Pases vendidos</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($corte['resumen']['pases']) }}</p>
        </div>
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Compras</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($corte['resumen']['compras']) }}</p>
        </div>
        <div class="card">
            <p class="titulo text-xs text-texto-suave">Hombres / Mujeres</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ number_format($corte['resumen']['hombres']) }}
                <span class="text-texto-suave">/</span>
                {{ number_format($corte['resumen']['mujeres']) }}
            </p>
        </div>
    </div>

    {{-- ═══ Por concepto ═══ --}}
    <div class="mt-6 overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
        <div class="border-b border-borde px-5 py-3">
            <p class="titulo text-sm text-jade">Desglose por concepto</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-borde text-left text-xs text-texto-suave">
                        <th class="px-4 py-2">Concepto</th>
                        <th class="px-4 py-2 text-right">Precio</th>
                        <th class="px-4 py-2 text-center">H / M</th>
                        <th class="px-4 py-2 text-right">Pases</th>
                        <th class="px-4 py-2 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($corte['por_rubro'] as $fila)
                        <tr class="border-b border-borde last:border-0">
                            <td class="px-4 py-2.5">{{ $fila->concepto }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $pesos((int) $fila->precio_centavos) }}</td>
                            <td class="px-4 py-2.5 text-center tabular-nums text-xs">
                                {{ $fila->hombres }} / {{ $fila->mujeres }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($fila->pases) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-medium">
                                {{ $pesos((int) $fila->total_centavos) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-texto-suave">
                                No hubo ingresos en el periodo seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($corte['por_rubro']->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-texto">
                            <td colspan="3" class="titulo px-4 py-3 text-sm">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">
                                {{ number_format($corte['resumen']['pases']) }}
                            </td>
                            <td class="px-4 py-3 text-right text-lg font-semibold tabular-nums">
                                {{ $pesos($corte['resumen']['total_centavos']) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- ═══ Por día ═══ --}}
        <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
            <div class="border-b border-borde px-5 py-3">
                <p class="titulo text-sm text-jade">Por día</p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-borde text-left text-xs text-texto-suave">
                        <th class="px-4 py-2">Día</th>
                        <th class="px-4 py-2 text-right">Compras</th>
                        <th class="px-4 py-2 text-right">Pases</th>
                        <th class="px-4 py-2 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($corte['por_dia'] as $fila)
                        <tr class="border-b border-borde last:border-0">
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($fila->dia)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format($fila->compras) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format($fila->pases) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $pesos((int) $fila->total_centavos) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-xs text-texto-suave">Sin movimientos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ═══ No cobrado ═══ --}}
        <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
            <div class="border-b border-borde px-5 py-3">
                <p class="titulo text-sm text-cinabrio">No cobrado</p>
                <p class="mt-1 text-[11px] text-texto-suave">
                    Explica la diferencia entre lo iniciado y lo que entró a caja.
                </p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-borde text-left text-xs text-texto-suave">
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2 text-right">Compras</th>
                        <th class="px-4 py-2 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($corte['no_cobradas'] as $fila)
                        <tr class="border-b border-borde last:border-0">
                            <td class="px-4 py-2">{{ str_replace('_', ' ', $fila->estado) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format($fila->compras) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-texto-suave">
                                {{ $pesos((int) $fila->total_centavos) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-xs text-texto-suave">
                            Todo lo iniciado se cobró.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-6 text-xs text-texto-suave">
        Los importes se calculan con el precio congelado al momento de cada compra. Cambiar una
        tarifa hoy no altera un corte anterior.
    </p>
@endsection
