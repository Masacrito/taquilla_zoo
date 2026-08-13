@extends('layouts.publico')

@section('titulo', 'Pago simulado')

@section('contenido')
    <div class="mx-auto max-w-lg">

        <div class="mb-6 rounded-input border-2 border-dashed border-magenta bg-magenta-suave px-4 py-3">
            <p class="titulo text-xs text-magenta">Entorno de pruebas</p>
            <p class="mt-1 text-xs text-texto">
                Esta pantalla sustituye a la del banco mientras no hay convenio ni credenciales.
                No se cobra dinero. Al confirmar se envía un webhook firmado, igual que en producción.
            </p>
        </div>

        <div class="card">
            <p class="titulo text-sm text-jade">Resumen del cobro</p>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-texto-suave">Folio</dt>
                    <dd class="font-medium">{{ $compra->folio }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-texto-suave">Fecha de visita</dt>
                    <dd>{{ $compra->fecha_visita->translatedFormat('d/m/Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-texto-suave">Pases</dt>
                    <dd>{{ $compra->pases_total }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-texto-suave">Referencia</dt>
                    <dd class="font-mono text-xs">{{ $pago->referencia_externa }}</dd>
                </div>
            </dl>

            <div class="mt-4 flex items-baseline justify-between border-t border-borde pt-4">
                <span class="titulo text-sm">Total</span>
                <span class="text-xl font-semibold tabular-nums">{{ $compra->totalFormateado() }}</span>
            </div>

            @if ($compra->estado !== \App\Models\Compra::PENDIENTE_PAGO)
                <p class="mt-5 text-sm text-texto-suave">
                    Esta compra ya no está pendiente de pago (estado: <strong>{{ $compra->estado }}</strong>).
                </p>
                <a href="{{ route('compras.retorno', $compra->folio) }}" class="btn-outline mt-3 w-full">Ver la compra</a>
            @else
                <form method="POST" action="{{ route('pago.simulado.confirmar', $pago->referencia_externa) }}"
                      class="mt-6 grid gap-2">
                    @csrf
                    <button type="submit" name="resultado" value="aprobar" class="btn-primary w-full">
                        Simular pago aprobado
                    </button>
                    <button type="submit" name="resultado" value="rechazar" class="btn-outline w-full">
                        Simular pago rechazado
                    </button>
                </form>
            @endif
        </div>

        <p class="mt-4 text-center text-xs text-texto-suave">
            El código QR se emite únicamente cuando el webhook confirma el pago, nunca desde esta pantalla.
        </p>
    </div>
@endsection
