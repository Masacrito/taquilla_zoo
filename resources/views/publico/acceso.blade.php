@extends('layouts.publico')

@section('titulo', 'Comprar boletos')

@section('contenido')
    <div class="mx-auto max-w-3xl">
        <h1 class="titulo text-lg">¿Cómo quieres comprar?</h1>
        <p class="mt-1 text-sm text-texto-suave">
            Puedes comprar con tu cuenta o sin registrarte. En los dos casos tus boletos llegan por correo.
        </p>

        <div class="mt-6 grid gap-4 md:grid-cols-2">

            {{-- ═══ Con cuenta ═══ --}}
            <div class="card flex flex-col">
                <p class="titulo text-sm text-jade">Con mi cuenta</p>
                <p class="mt-2 text-sm text-texto-suave">
                    Tus compras quedan guardadas y puedes volver a ver tus códigos QR cuando quieras.
                </p>

                <div class="mt-5 grid gap-2 md:mt-auto md:pt-5">
                    <a href="{{ route('portal.ingresar') }}" class="btn-primary w-full">Ingresar</a>
                    <a href="{{ route('portal.registro') }}" class="btn-outline w-full">Crear cuenta</a>
                </div>
            </div>

            {{-- ═══ Como invitado ═══ --}}
            <form method="POST" action="{{ route('invitado.solicitar') }}" class="card flex flex-col">
                @csrf
                <p class="titulo text-sm text-jade">Como invitado</p>
                <p class="mt-2 text-sm text-texto-suave">
                    Sin crear cuenta. Solo necesitamos un correo para enviarte tus boletos.
                </p>

                <div class="mt-5">
                    <label class="label" for="correo">
                        Ingrese una dirección de correo electrónico para enviarle sus boletos
                    </label>
                    <input type="email" id="correo" name="correo" class="input" value="{{ old('correo') }}"
                           required maxlength="160" autocomplete="email" placeholder="nombre@correo.com">
                </div>

                <p class="mt-2 text-xs text-texto-suave">
                    Te enviaremos un código de 6 dígitos para confirmar que el correo es tuyo.
                </p>

                <div class="mt-5 md:mt-auto md:pt-5">
                    <button type="submit" class="btn-primary w-full">Continuar como invitado</button>
                </div>
            </form>
        </div>
    </div>
@endsection
