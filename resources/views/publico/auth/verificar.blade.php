@extends('layouts.publico')

@section('titulo', 'Verifica tu correo')

@section('contenido')
    <div class="mx-auto max-w-sm">
        <h1 class="titulo mb-1 text-lg">Verifica tu correo</h1>
        <p class="mb-6 text-sm text-texto-suave">
            Escribe el código de 6 dígitos que te enviamos. Vence en 10 minutos.
        </p>

        <form method="POST" action="{{ route('portal.verificar') }}" class="card grid gap-4">
            @csrf

            <div>
                <label class="label">Correo</label>
                <input type="email" name="correo" class="input"
                       value="{{ old('correo', $correo) }}" required readonly>
            </div>

            <div>
                <label class="label">Código</label>
                <input type="text" name="codigo" class="input text-center text-2xl tracking-[0.4em]"
                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       autocomplete="one-time-code" required autofocus>
            </div>

            <button type="submit" class="btn-primary w-full">Verificar</button>
        </form>

        <form method="POST" action="{{ route('portal.reenviar') }}" class="mt-4 text-center">
            @csrf
            <input type="hidden" name="correo" value="{{ old('correo', $correo) }}">
            <button type="submit" class="text-sm text-jade hover:underline">
                No me llegó, enviar otro código
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-texto-suave">
            Si no llega, revisa tu carpeta de spam o correo no deseado.
        </p>
    </div>
@endsection
