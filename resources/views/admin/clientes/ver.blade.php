@extends('layouts.interno')

@section('titulo', $cliente->nombreCompleto())
@section('subtitulo', 'Ficha del visitante')

@section('contenido')

    <a href="{{ route('admin.clientes.index') }}" class="mb-4 inline-block text-sm text-jade hover:underline">
        ← Visitantes
    </a>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-1">
            <p class="titulo text-sm text-jade">Datos</p>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-texto-suave">Correo</dt>
                    <dd>{{ $cliente->correo }}</dd>
                    <dd class="mt-1">
                        @if ($cliente->correoVerificado())
                            <span class="badge-activo">verificado</span>
                        @else
                            <span class="badge-inactivo">sin verificar</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Teléfono</dt>
                    <dd>{{ $cliente->telefono }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Fecha de nacimiento</dt>
                    <dd>{{ $cliente->fecha_nacimiento->translatedFormat('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Género</dt>
                    <dd>{{ $cliente->genero }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Forma de ingreso</dt>
                    <dd>{{ $cliente->proveedor_oauth ? 'Google' : 'Correo y contraseña' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Registrado</dt>
                    <dd>{{ $cliente->created_at->translatedFormat('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
                <div class="border-b border-borde px-5 py-3">
                    <p class="titulo text-sm text-jade">Compras ({{ $cliente->compras->count() }})</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-borde text-left text-xs text-texto-suave">
                                <th class="px-4 py-2">Folio</th>
                                <th class="px-4 py-2">Visita</th>
                                <th class="px-4 py-2 text-center">Pases</th>
                                <th class="px-4 py-2 text-right">Total</th>
                                <th class="px-4 py-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cliente->compras as $compra)
                                <tr class="border-b border-borde last:border-0 hover:bg-jade/4">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('admin.compras.ver', $compra) }}"
                                           class="font-medium text-jade hover:underline">{{ $compra->folio }}</a>
                                    </td>
                                    <td class="px-4 py-2.5">{{ $compra->fecha_visita->translatedFormat('d/m/Y') }}</td>
                                    <td class="px-4 py-2.5 text-center tabular-nums">
                                        {{ $compra->pases_usados }}/{{ $compra->pases_total }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right tabular-nums">{{ $compra->totalFormateado() }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="{{ $compra->estaPagada() ? 'badge-activo' : 'badge-especial' }}">
                                            {{ str_replace('_', ' ', $compra->estado) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-texto-suave">
                                        Este visitante todavía no ha comprado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
