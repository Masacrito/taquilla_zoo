@extends('layouts.publico')

@section('titulo', 'Procesando tu pago')

@section('contenido')
    <div class="mx-auto max-w-md text-center">

        @if ($compra->estaPagada())
            <div class="card">
                <p class="titulo text-sm text-jade">Pago confirmado</p>
                <p class="mt-3 text-sm text-texto-suave">
                    Tu compra <strong>{{ $compra->folio }}</strong> quedó pagada. Te enviamos el
                    comprobante con tu código QR por correo.
                </p>
                <a href="{{ route('compras.ver', $compra->folio) }}" class="btn-primary mt-5 w-full">
                    Ver mi código QR
                </a>
            </div>

        @elseif ($compra->estado === \App\Models\Compra::PENDIENTE_PAGO)
            {{-- Esta pantalla NO emite el QR ni marca nada como pagado: solo
                 refleja lo que el webhook haya escrito (brief §4.4). --}}
            <div class="card">
                <p class="titulo text-sm">Estamos confirmando tu pago</p>
                <p class="mt-3 text-sm text-texto-suave">
                    Tu compra <strong>{{ $compra->folio }}</strong> está pendiente de confirmación
                    por parte del banco. Esto puede tardar unos segundos.
                </p>
                <p class="mt-2 text-xs text-texto-suave">
                    No cierres esta página ni vuelvas a pagar. Si pasan más de 15 minutos sin
                    confirmarse, la compra se cancela y los lugares se liberan.
                </p>
                <a href="{{ route('compras.retorno', $compra->folio) }}" class="btn-outline mt-5 w-full">
                    Actualizar
                </a>
            </div>

        @else
            <div class="card">
                <p class="titulo text-sm text-cinabrio">Compra no completada</p>
                <p class="mt-3 text-sm text-texto-suave">
                    La compra <strong>{{ $compra->folio }}</strong> quedó en estado
                    <strong>{{ $compra->estado }}</strong>. Los lugares se liberaron.
                </p>
                <a href="{{ route('compras.crear') }}" class="btn-primary mt-5 w-full">
                    Intentar de nuevo
                </a>
            </div>
        @endif

        <a href="{{ route('compras.index') }}" class="mt-4 inline-block text-sm text-jade hover:underline">
            Ver todas mis compras
        </a>
    </div>
@endsection
