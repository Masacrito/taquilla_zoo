@extends('layouts.interno')

@section('titulo', 'Mi cuenta')
@section('subtitulo', $cuenta->username . ' · ' . $cuenta->rol->nombre)

@section('contenido')
    <div class="grid max-w-3xl gap-6 md:grid-cols-2">

        <form method="POST" action="{{ route('cuenta.actualizar') }}" class="card grid content-start gap-4">
            @csrf @method('PUT')
            <h2 class="titulo text-sm">Mis datos</h2>

            <div>
                <label class="label" for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" class="input" required maxlength="120"
                       value="{{ old('nombre', $cuenta->usuario->nombre) }}">
            </div>
            <div>
                <label class="label" for="email">Correo</label>
                <input type="email" id="email" name="email" class="input" maxlength="160"
                       value="{{ old('email', $cuenta->usuario->email) }}">
                @if ($cuenta->esSuperAdmin())
                    <p class="mt-1.5 text-xs text-texto-suave">
                        A este correo llegan los avisos de fallos del sistema.
                    </p>
                @endif
            </div>

            <div><button type="submit" class="btn-primary">Guardar datos</button></div>
        </form>

        <form method="POST" action="{{ route('cuenta.password') }}" class="card grid content-start gap-4">
            @csrf @method('PUT')
            <h2 class="titulo text-sm">Cambiar contraseña</h2>

            <div>
                <label class="label" for="password_actual">Contraseña actual</label>
                <input type="password" id="password_actual" name="password_actual" class="input"
                       required autocomplete="current-password">
            </div>
            <div>
                <label class="label" for="password">Contraseña nueva</label>
                <input type="password" id="password" name="password" class="input"
                       required minlength="10" autocomplete="new-password">
                <p class="mt-1.5 text-xs text-texto-suave">Mínimo 10 caracteres, con letras y números.</p>
            </div>
            <div>
                <label class="label" for="password_confirmation">Repite la contraseña nueva</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="input"
                       required minlength="10" autocomplete="new-password">
            </div>

            <div><button type="submit" class="btn-primary">Cambiar contraseña</button></div>

            @if ($cuenta->password_cambiado_en)
                <p class="text-xs text-texto-suave">
                    Último cambio: {{ $cuenta->password_cambiado_en->translatedFormat('j \d\e F \d\e Y, H:i') }}
                </p>
            @endif
        </form>
    </div>
@endsection
