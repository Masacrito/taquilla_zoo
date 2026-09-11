@extends('errors.minimo')

@section('codigo', '419')
@section('titulo', 'Tu sesión expiró')

@section('mensaje')
    Pasó demasiado tiempo desde que abriste la página y, por seguridad, la sesión se cerró sola. No se perdió nada: vuelve a entrar y continúa.
@endsection

@section('acciones')
            <a href="{{ route('portal.ingresar') }}" class="btn-primary">Volver a entrar</a>
            <a href="{{ route('portal.inicio') }}" class="btn-outline">Ir al inicio</a>
@endsection

@section('nota')
    Si estabas comprando boletos, tendrás que elegir la fecha y las cantidades otra vez. No se generó ningún cobro.
@endsection
