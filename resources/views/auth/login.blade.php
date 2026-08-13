@extends('layouts.auth')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <div class="card">
        <p class="mb-4 text-xs text-texto-suave">
            Entra con tu <strong>nombre de usuario</strong>, no con tu correo.
            ¿Vienes a comprar boletos?
            <a href="{{ route('portal.inicio') }}" class="text-jade hover:underline">Ve al portal de visitantes</a>.
        </p>

        @if ($errors->any())
            <div class="flash-error">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="username" class="label">Usuario</label>
                <input type="text" id="username" name="username" class="input"
                       value="{{ old('username') }}" required autofocus autocomplete="username">
            </div>

            <div>
                <label for="password" class="label">Contraseña</label>
                <input type="password" id="password" name="password" class="input"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-primary w-full">Entrar</button>
        </form>
    </div>
@endsection
