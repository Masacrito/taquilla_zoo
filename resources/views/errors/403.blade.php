@extends('errors.minimo')

@section('codigo', '403')
@section('titulo', 'No tienes acceso aquí')

@section('mensaje')
    Tu cuenta no tiene permiso para ver esta sección. Si crees que deberías tenerlo, pídeselo a quien administra el sistema.
@endsection

@section('acciones')
            <a href="{{ route('portal.inicio') }}" class="btn-primary">Ir al inicio</a>
@endsection
