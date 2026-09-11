@extends('errors.minimo')

@section('codigo', '429')
@section('titulo', 'Demasiados intentos')

@section('mensaje')
    Hiciste muchas peticiones en poco tiempo y el sistema las frenó por seguridad. Espera un minuto y vuelve a intentarlo.
@endsection

@section('acciones')
            <a href="{{ route('portal.inicio') }}" class="btn-primary">Ir al inicio</a>
@endsection
