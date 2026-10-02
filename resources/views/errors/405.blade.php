@extends('errors.minimo')

@section('codigo', '405')
@section('titulo', 'Esa acción no se puede abrir así')

@section('mensaje')
    Llegaste a una dirección que no se visita directamente: es parte de un formulario
    o de un botón del sistema. Vuelve al inicio y hazlo desde ahí.
@endsection

@section('acciones')
            <a href="{{ route('portal.inicio') }}" class="btn-primary">Ir al inicio</a>
@endsection

@section('nota')
    Suele pasar al recargar una página después de enviar un formulario, o al abrir un
    enlace guardado en favoritos que en realidad era una acción.
@endsection
