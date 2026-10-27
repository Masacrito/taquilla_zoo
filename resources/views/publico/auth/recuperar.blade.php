@extends('layouts.publico')

@section('titulo', 'Recuperar contraseña')

@section('contenido')
    <div class="mx-auto max-w-sm">
        <h1 class="titulo mb-2 text-lg">¿Olvidaste tu contraseña?</h1>
        <p class="mb-6 text-sm text-texto-suave">
            Escribe el correo de tu cuenta y te enviaremos un enlace para elegir una nueva.
        </p>

        <form method="POST" action="{{ route('portal.recuperar') }}" class="card grid gap-4">
            @csrf

            <div>
                <label class="label">Correo</label>
                <input type="email" name="correo" class="input" value="{{ old('correo') }}"
                       required autofocus autocomplete="email">
            </div>

            <button type="submit" class="btn-primary w-full">Enviar enlace</button>
        </form>

        <p class="mt-4 text-center text-sm text-texto-suave">
            <a href="{{ route('portal.ingresar') }}" class="text-jade hover:underline">Volver a ingresar</a>
        </p>
    </div>
@endsection
