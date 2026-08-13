@extends('layouts.interno')

@section('titulo', 'Visitantes')
@section('subtitulo', 'Personas registradas en el portal')

@section('contenido')

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1">
            <label class="label">Buscar</label>
            <input type="text" name="q" class="input" value="{{ $busqueda }}"
                   placeholder="Correo, nombre o apellidos">
        </div>
        <div>
            <label class="label">Estado del correo</label>
            <select name="estado" class="input min-w-40">
                <option value="">Todos</option>
                <option value="verificados" @selected(request('estado') === 'verificados')>Verificados</option>
                <option value="sin_verificar" @selected(request('estado') === 'sin_verificar')>Sin verificar</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">Buscar</button>
        @if (request()->hasAny(['q', 'estado']))
            <a href="{{ route('admin.clientes.index') }}" class="btn-outline">Limpiar</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="titulo bg-jade text-left text-[11px] text-white">
                        <th class="px-4 py-3">Visitante</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Ingreso</th>
                        <th class="px-4 py-3 text-center">Compras</th>
                        <th class="px-4 py-3">Registro</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $cliente)
                        <tr class="border-b border-borde transition-colors last:border-0 hover:bg-jade/4">
                            <td class="px-4 py-2.5">
                                <p class="font-medium">{{ $cliente->nombreCompleto() }}</p>
                                <p class="text-xs text-texto-suave">{{ $cliente->telefono }}</p>
                            </td>
                            <td class="px-4 py-2.5">
                                {{ $cliente->correo }}
                                <br>
                                @if ($cliente->correoVerificado())
                                    <span class="badge-activo">verificado</span>
                                @else
                                    <span class="badge-inactivo">sin verificar</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-texto-suave">
                                {{ $cliente->proveedor_oauth ? 'Google' : 'Correo y contraseña' }}
                            </td>
                            <td class="px-4 py-2.5 text-center tabular-nums">{{ $cliente->compras_count }}</td>
                            <td class="px-4 py-2.5 text-xs text-texto-suave">
                                {{ $cliente->created_at->translatedFormat('d/m/Y') }}
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.clientes.ver', $cliente) }}" class="btn-outline btn-sm">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-texto-suave">
                                No hay visitantes que coincidan con la búsqueda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>
@endsection
