<x-mail::message>
# Tu visita es {{ $esHoy ? 'hoy' : 'mañana' }}

{{ $compra->cliente ? 'Hola ' . $compra->cliente->nombre . ', te' : 'Te' }} recordamos que tienes boletos para visitar el zoológico.

**Fecha de visita:** {{ $compra->fecha_visita->translatedFormat('l d \d\e F \d\e Y') }}
**Horario:** {{ $horario['apertura'] }} a {{ $horario['cierre'] }} hrs
**Pases:** {{ $compra->pases_total }}
**Folio:** {{ $compra->folio }}

@if ($esHoy)
El zoológico cierra a las {{ $horario['cierre'] }} hrs. Llega con tiempo para recorrerlo con calma.
@endif

<x-mail::button :url="$compra->urlDetalle()">
Ver mi código de acceso
</x-mail::button>

## Antes de salir

- Ten a la mano tu código QR: está en el comprobante que te enviamos y en el botón de arriba.
- El boleto vale solo para la fecha de visita. Consulta las [políticas de entrega, cancelación y reembolso]({{ route('portal.politicas') }}).
- Tercera edad presenta INAPAM. Estudiante presenta credencial.

Te esperamos,
**Zoológico Regional Miguel Álvarez del Toro**

<x-slot:subcopy>
Recibes este aviso porque tienes una compra pagada para esa fecha.
Secretaría de Medio Ambiente e Historia Natural · Gobierno del Estado de Chiapas.
</x-slot:subcopy>
</x-mail::message>
