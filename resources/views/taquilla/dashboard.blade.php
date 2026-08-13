@extends('layouts.interno')

@section('titulo', 'Panel taquilla')
@section('subtitulo', 'Bienvenido, ' . auth()->user()->usuario->nombre)

@section('contenido')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card">
            <p class="titulo text-sm text-jade">Accesos</p>
            <p class="mt-2 text-sm text-texto-suave">
                Escaneo de códigos QR y bitácora de entradas.
            </p>
            <span class="badge-especial mt-3">Próximamente</span>
        </div>

        <div class="card">
            <p class="titulo text-sm text-jade">Cortes</p>
            <p class="mt-2 text-sm text-texto-suave">
                Corte de ingresos del día.
            </p>
            <span class="badge-especial mt-3">Próximamente</span>
        </div>
    </div>

    <div class="card mt-6">
        <p class="titulo text-xs text-texto-suave">Horario de operación</p>
        <p class="mt-2 text-sm">Martes a domingo, 8:30 a 16:00 hrs. <strong>Lunes cerrado.</strong></p>
    </div>
@endsection
