@extends('layouts.interno')

@section('titulo', 'Panel administrador')
@section('subtitulo', 'Bienvenido, ' . auth()->user()->usuario->nombre)

@section('contenido')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if (auth()->user()->puedeGestionarUsuarios())
            <a href="{{ route('admin.users.index') }}" class="card transition-shadow hover:shadow-card">
                <p class="titulo text-sm text-jade">Usuarios</p>
                <p class="mt-2 text-sm text-texto-suave">
                    Altas, roles, permisos y estado de las cuentas internas.
                </p>
            </a>
        @endif
    </div>

    <p class="mt-8 text-xs text-texto-suave">
        Los módulos de rubros, catálogos, aforo, cortes y estadísticas se habilitan en la
        siguiente fase.
    </p>
@endsection
