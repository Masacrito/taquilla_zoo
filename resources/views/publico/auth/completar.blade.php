@extends('layouts.publico')

@section('titulo', 'Completa tu registro')

@section('contenido')
    <div class="mx-auto max-w-xl">
        <h1 class="titulo mb-1 text-lg">Completa tu registro</h1>
        <p class="mb-6 text-sm text-texto-suave">
            Google nos compartió tu correo y tu nombre. Faltan unos datos que necesitamos
            para emitir tus boletos.
        </p>

        <div class="mb-4 flex items-center gap-3 rounded-input bg-jade-suave px-4 py-3 text-sm">
            <span class="titulo text-xs text-jade">Verificado con Google</span>
            <span class="text-texto">{{ $pendiente['correo'] }}</span>
        </div>

        <form method="POST" action="{{ route('portal.completar') }}" class="card grid gap-4 sm:grid-cols-2">
            @csrf

            <div>
                <label class="label">Nombre(s)</label>
                <input type="text" name="nombre" class="input"
                       value="{{ old('nombre', $pendiente['nombre']) }}" required>
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

            <div class="sm:col-span-2">
                <button type="submit" class="btn-primary w-full">Terminar y entrar</button>
            </div>
        </form>

        <p class="mt-4 text-center text-xs text-texto-suave">
            No necesitas contraseña: entrarás siempre con Google.
        </p>
    </div>
@endsection
