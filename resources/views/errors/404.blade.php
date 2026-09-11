@extends('errors.minimo')

@section('codigo', '404')
@section('titulo', 'Esta página no existe')

@section('mensaje')
    La dirección que abriste no corresponde a ninguna página del sistema. Puede que el enlace esté mal escrito o que la página se haya movido.
@endsection

@section('acciones')
            <a href="{{ route('portal.inicio') }}" class="btn-primary">Ir al inicio</a>
@endsection
