@extends('layouts.publico')

@section('titulo', 'Contraseña nueva')

@section('contenido')
    <div class="mx-auto max-w-sm">
        <h1 class="titulo mb-6 text-lg">Elige tu contraseña nueva</h1>

        <form method="POST" action="{{ route('portal.restablecer', $token) }}" class="card grid gap-4">
            @csrf

            <div>
                <label class="label">Correo de tu cuenta</label>
                <input type="email" name="correo" class="input" value="{{ old('correo') }}"
                       required autofocus autocomplete="email">
            </div>

            <div>
                <label class="label">Contraseña nueva</label>
                <input type="password" name="password" class="input" required minlength="8" autocomplete="new-password">
                <p class="mt-1 text-xs text-texto-suave">Mínimo 8 caracteres, con letras y números.</p>
            </div>

            <div>
                <label class="label">Confirma la contraseña</label>
                <input type="password" name="password_confirmation" class="input" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary w-full">Guardar contraseña</button>
        </form>
    </div>
@endsection
