@component('mail::message')
# Algo se rompió

El sistema de taquilla registró una falla.

@component('mail::table')
| | |
|:---|:---|
| **Tipo** | {{ $error->claseCorta() }} |
| **Mensaje** | {{ Str::limit($error->mensaje, 300) }} |
| **Dónde** | {{ basename((string) $error->archivo) }}:{{ $error->linea }} |
@if ($error->url)
| **Ruta** | {{ $error->metodo }} {{ $error->url }} |
@endif
| **Cuándo** | {{ $error->ultima_vez->translatedFormat('d/m/Y H:i') }} hrs |
@if ($error->ocurrencias > 1)
| **Veces** | {{ number_format($error->ocurrencias) }} |
@endif
@endcomponent

@component('mail::button', ['url' => $url])
Ver el detalle en el panel
@endcomponent

La traza completa no viaja en este correo: está en el panel, detrás de tu
sesión. Un correo se reenvía sin pensarlo, y ahí se leen rutas del servidor.

@if ($error->ocurrencias > 1)
Este tipo de falla ya se había registrado antes. El aviso se repite cuando
vuelve a ocurrir, y no más de una vez cada media hora, para no llenarte el
buzón si algo entra en bucle.
@endif

@endcomponent
