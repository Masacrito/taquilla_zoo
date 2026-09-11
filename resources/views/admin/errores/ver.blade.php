@extends('layouts.interno')

@section('titulo', $error->claseCorta())
@section('subtitulo', 'Detalle del fallo')

@section('contenido')
    <div class="mx-auto max-w-3xl">

        <a href="{{ route('admin.errores.index') }}" class="mb-4 inline-block text-sm text-jade hover:underline">
            ← Fallos del sistema
        </a>

        <div class="card">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-64 flex-1">
                    <p class="titulo text-sm text-jade">{{ $error->claseCorta() }}</p>
                    <p class="mt-2 text-sm leading-relaxed">{{ $error->mensaje }}</p>
                </div>

                @if ($error->estaAtendido())
                    <span class="badge-inactivo shrink-0">atendido</span>
                @else
                    <span class="badge-especial shrink-0">pendiente</span>
                @endif
            </div>

            <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-texto-suave">Dónde</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs">{{ $error->archivo }}:{{ $error->linea }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Ocurrencias</dt>
                    <dd class="mt-0.5 tabular-nums">{{ number_format($error->ocurrencias) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Primera vez</dt>
                    <dd class="mt-0.5">{{ $error->primera_vez->translatedFormat('d/m/Y H:i') }} hrs</dd>
                </div>
                <div>
                    <dt class="text-xs text-texto-suave">Última vez</dt>
                    <dd class="mt-0.5">{{ $error->ultima_vez->translatedFormat('d/m/Y H:i') }} hrs</dd>
                </div>
                @if ($error->url)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-texto-suave">Ruta</dt>
                        <dd class="mt-0.5 break-all font-mono text-xs">{{ $error->metodo }} {{ $error->url }}</dd>
                    </div>
                @endif
                @if ($error->codigo)
                    <div>
                        <dt class="text-xs text-texto-suave">Estado HTTP</dt>
                        <dd class="mt-0.5 tabular-nums">{{ $error->codigo }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs text-texto-suave">Quién lo encontró</dt>
                    <dd class="mt-0.5">
                        @if ($error->cuenta)
                            {{ $error->cuenta->usuario->nombre ?? $error->cuenta->username }} (personal)
                        @elseif ($error->id_cliente)
                            Un visitante con sesión
                        @else
                            Sin sesión, o un proceso en segundo plano
                        @endif
                    </dd>
                </div>
                @if ($error->ip)
                    <div>
                        <dt class="text-xs text-texto-suave">Origen</dt>
                        <dd class="mt-0.5 font-mono text-xs">{{ $error->ip }}</dd>
                    </div>
                @endif
            </dl>

            @if ($error->estaAtendido())
                <p class="mt-5 rounded-input bg-arena/25 px-4 py-3 text-xs text-texto-suave">
                    Marcado como atendido el
                    {{ $error->atendido_en->translatedFormat('d/m/Y H:i') }} hrs
                    @if ($error->atendidoPor)
                        por {{ $error->atendidoPor->usuario->nombre ?? $error->atendidoPor->username }}
                    @endif.
                </p>
            @endif

            <div class="mt-5 flex flex-wrap gap-3 border-t border-borde pt-5">
                @if ($error->estaAtendido())
                    <form method="POST" action="{{ route('admin.errores.reabrir', $error) }}">
                        @csrf @method('PUT')
                        <button type="submit" class="btn-outline btn-sm">Volver a pendientes</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.errores.atender', $error) }}">
                        @csrf @method('PUT')
                        <button type="submit" class="btn-primary btn-sm">Marcar como atendido</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($error->traza)
            <div class="card mt-4">
                <details>
                    <summary class="titulo cursor-pointer text-sm text-jade">Traza</summary>
                    <pre class="mt-4 overflow-x-auto rounded-input bg-texto p-4 text-[11px] leading-relaxed text-white">{{ $error->traza }}</pre>
                </details>
                <p class="mt-3 text-xs text-texto-suave">
                    La traza no viaja en el correo de aviso: enseña rutas del servidor y estructura
                    interna, y un correo se reenvía sin pensarlo. Vive aquí, detrás de tu sesión.
                </p>
            </div>
        @endif

        @if ($error->navegador)
            <p class="mt-4 break-all text-[11px] text-texto-suave">{{ $error->navegador }}</p>
        @endif
    </div>
@endsection
