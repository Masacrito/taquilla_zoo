<x-mail::message>
# Tu compra está confirmada

Hola {{ $compra->cliente->nombre }}, tu pago se registró correctamente.

**Folio:** {{ $compra->folio }}
**Fecha de visita:** {{ $compra->fecha_visita->translatedFormat('l d \d\e F \d\e Y') }}
**Pases:** {{ $compra->pases_total }}
**Total:** {{ $compra->totalFormateado() }}

## Tu código de acceso

Presenta este código en el acceso del zoológico. También lo encuentras en el
PDF adjunto y en tu cuenta.

<div style="text-align:center; margin:24px 0;">
{!! $qrSvg !!}
</div>

<x-mail::table>
| Concepto | Cantidad | Importe |
|:---------|:--------:|--------:|
@foreach ($compra->detalle as $renglon)
| {{ $renglon->rubro_nombre_snap }} | {{ $renglon->cantidad }} | {{ $renglon->importeFormateado() }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('compras.ver', $compra->folio)">
Ver mi compra
</x-mail::button>

## Antes de tu visita

- **Horario: martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.**
- Verifica que el tipo de visitante sea el correcto: se valida en el acceso y, de lo contrario, se paga boleto.
- Tercera edad presenta INAPAM. Estudiante presenta credencial. Niño Pavón gratis hasta 1.20 m de estatura.

Gracias por tu visita,
**Zoológico Regional Miguel Álvarez del Toro**

<x-slot:subcopy>
Si no reconoces esta compra, responde a este correo.
Secretaría de Medio Ambiente e Historia Natural · Gobierno del Estado de Chiapas.
</x-slot:subcopy>
</x-mail::message>
