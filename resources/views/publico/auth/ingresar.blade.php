@extends('layouts.publico')

@section('titulo', 'Ingresar')

@section('contenido')
    <div class="mx-auto max-w-sm">
        <h1 class="titulo mb-6 text-lg">Ingresar</h1>

        <form method="POST" action="{{ route('portal.ingresar') }}" class="card grid gap-4">
            @csrf

            <div>
                <label class="label">Correo</label>
                <input type="email" name="correo" class="input" value="{{ old('correo') }}"
                       required autofocus autocomplete="email">
            </div>

            <div>
                <label class="label">Contraseña</label>
                <input type="password" name="password" class="input" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-primary w-full">Entrar</button>
        </form>

        {{-- Solo si hay credenciales de Google configuradas --}}
        @if (filled(config('services.google.client_id')))
            <div class="my-5 flex items-center gap-3">
                <span class="h-px flex-1 bg-borde"></span>
                <span class="text-xs text-texto-suave">o</span>
                <span class="h-px flex-1 bg-borde"></span>
            </div>

            <a href="{{ route('portal.google') }}"
               class="btn-outline flex w-full items-center justify-center gap-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.05l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/>
                </svg>
                Continuar con Google
            </a>
        @endif

        <p class="mt-4 text-center text-sm text-texto-suave">
            ¿No tienes cuenta?
            <a href="{{ route('portal.registro') }}" class="text-jade hover:underline">Créala aquí</a>
        </p>
    </div>
@endsection
