@extends('errors.minimo')

@section('codigo', '500')
@section('titulo', 'Algo salió mal de nuestro lado')

@section('mensaje')
    No es culpa tuya: ocurrió una falla en el sistema. Ya quedó registrada y el área técnica recibió el aviso.
@endsection

@section('acciones')
            <a href="{{ route('portal.inicio') }}" class="btn-primary">Ir al inicio</a>
@endsection

@section('nota')
    Si estabas pagando, NO vuelvas a pagar. Revisa primero en «Mis compras»: si el cobro se procesó, tu boleto está ahí.
@endsection
