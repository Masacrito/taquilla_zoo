@extends('layouts.publico')

@section('titulo', 'Crear cuenta')

@section('contenido')
    <div class="mx-auto max-w-xl">
        <h1 class="titulo mb-1 text-lg">Crear cuenta</h1>
        <p class="mb-6 text-sm text-texto-suave">
            Te enviaremos un código de 6 dígitos para confirmar tu correo.
        </p>

        {{-- Vía rápida: con Google no hace falta contraseña ni código, porque
             Google ya comprobó la posesión del correo. --}}
        @if (filled(config('services.google.client_id')))
            <a href="{{ route('portal.google') }}"
               class="btn-outline mb-2 flex w-full items-center justify-center gap-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.05l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/>
                </svg>
                Continuar con Google
            </a>
            <p class="mb-5 text-center text-xs text-texto-suave">
                Más rápido: sin contraseña y sin código de verificación.
            </p>

            <div class="mb-5 flex items-center gap-3">
                <span class="h-px flex-1 bg-borde"></span>
                <span class="text-xs text-texto-suave">o regístrate con tu correo</span>
                <span class="h-px flex-1 bg-borde"></span>
            </div>
        @endif

        <form method="POST" action="{{ route('portal.registro') }}" class="card grid gap-4 sm:grid-cols-2">
            @csrf

            <div class="sm:col-span-2">
                <label class="label">Correo electrónico</label>
                <input type="email" name="correo" class="input" value="{{ old('correo') }}"
                       required autocomplete="email">
            </div>

            <div>
                <label class="label">Nombre(s)</label>
                <input type="text" name="nombre" class="input" value="{{ old('nombre') }}" required>
            </div>
            <div>
                <label class="label">Apellidos</label>
                <input type="text" name="apellidos" class="input" value="{{ old('apellidos') }}" required>
            </div>

            <div>
                <label class="label">Fecha de nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="input"
                       value="{{ old('fecha_nacimiento') }}" required>
            </div>
            <div>
                <label class="label">Género</label>
                <select name="genero" class="input" required>
                    <option value="">— Seleccionar —</option>
                    @foreach (['Mujer', 'Hombre', 'No binario', 'Prefiero no decirlo'] as $g)
                        <option value="{{ $g }}" @selected(old('genero') === $g)>{{ $g }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label">Teléfono</label>
                <input type="tel" name="telefono" class="input" value="{{ old('telefono') }}" required>
            </div>
            <div>
                <label class="label">País</label>
                <select name="id_pais" class="input">
                    <option value="">— Opcional —</option>
                    @foreach ($paises as $p)
                        <option value="{{ $p->id }}" @selected(old('id_pais') == $p->id)>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="label">Estado</label>
                <select name="id_estado" class="input">
                    <option value="">— Opcional —</option>
                    @foreach ($estados as $e)
                        <option value="{{ $e->id }}" @selected(old('id_estado') == $e->id)>{{ $e->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label">Contraseña</label>
                <input type="password" name="password" class="input" required autocomplete="new-password">
                <p class="mt-1 text-[11px] text-texto-suave">Mínimo 8 caracteres, con letras y números.</p>
            </div>
            <div>
                <label class="label">Repite la contraseña</label>
                <input type="password" name="password_confirmation" class="input" required autocomplete="new-password">
            </div>

            <div class="sm:col-span-2">
                <button type="submit" class="btn-primary w-full">Crear cuenta</button>
            </div>
        </form>

        <p class="mt-4 text-center text-sm text-texto-suave">
            ¿Ya tienes cuenta?
            <a href="{{ route('portal.ingresar') }}" class="text-jade hover:underline">Ingresa aquí</a>
        </p>
    </div>
@endsection
