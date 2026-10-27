<x-mail::message>
# Tu compra está confirmada

{{ $compra->cliente ? 'Hola ' . $compra->cliente->nombre . ', tu' : 'Tu' }} pago se registró correctamente.

**Folio:** {{ $compra->folio }}
**Fecha de visita:** {{ $compra->fecha_visita->translatedFormat('l d \d\e F \d\e Y') }}
**Pases:** {{ $compra->pases_total }}
**Total:** {{ $compra->totalFormateado() }}

@if ($compra->fecha_visita->isToday())
{{-- Hace las veces de recordatorio para quien compra para hoy (2026-10-27):
     el aviso del mismo día espera una hora desde el pago y quien compra
     tarde ya no lo alcanzaría. --}}
<div style="margin:20px 0; padding:14px 16px; background:#fdecea; border:1px solid #e8a3ad;
            border-radius:10px; color:#1f2933; font-size:14px;">
<strong style="color:#ae192d;">Tu visita es hoy.</strong> Toma en cuenta tu hora de llegada:
el zoológico cierra a las <strong>{{ config('taquilla.horario.cierre') }} hrs</strong>.
</div>
@endif

## Tu código de acceso

Presenta este código en el acceso del zoológico. También lo encuentras en el
PDF adjunto y en el botón «Ver mi compra» de este correo.

<div style="text-align:center; margin:24px 0;">
<img src="{{ $message->embedData($qrPng, 'codigo-' . $compra->folio . '.png', 'image/png') }}"
     alt="Código QR {{ $compra->folio }}"
     width="260" height="260"
     style="display:block; margin:0 auto; border:1px solid #e5e7eb; border-radius:10px;">
<p style="margin:10px 0 0; font-size:13px; color:#6b7280;">{{ $compra->folio }}</p>
</div>

<x-mail::table>
| Concepto | Cantidad | Importe |
|:---------|:--------:|--------:|
@foreach ($compra->detalle as $renglon)
| {{ $renglon->rubro_nombre_snap }} | {{ $renglon->cantidad }} | {{ $renglon->importeFormateado() }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$compra->urlDetalle()">
Ver mi compra
</x-mail::button>

## Antes de tu visita

- **Horario: martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.**
- Verifica que el tipo de visitante sea el correcto: se valida en el acceso y, de lo contrario, se paga boleto.
@foreach ($compra->requisitosDeAcceso() as $tipo => $requisito)
- **{{ $tipo }}:** {{ $requisito }}
@endforeach
- El boleto vale solo para la fecha de visita. Consulta las [políticas de entrega, cancelación y reembolso]({{ route('portal.politicas') }}).

Gracias por tu visita,
**Zoológico Regional Miguel Álvarez del Toro**

<x-slot:subcopy>
Si no reconoces esta compra, responde a este correo.
Secretaría de Medio Ambiente e Historia Natural · Gobierno del Estado de Chiapas.
</x-slot:subcopy>
</x-mail::message>
