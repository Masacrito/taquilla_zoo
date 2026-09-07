@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
{{-- Se quitó la rama que mostraba el logo de Laravel: cargaba la imagen desde
     laravel.com, lo que revela a un tercero cuándo y quién abre cada
     comprobante.

     El logo institucional se incrusta como adjunto en línea (cid:) y no como
     URL: el correo va a bandejas externas, donde una ruta a este servidor no
     resolvería. `$message` solo existe cuando el correo se está armando de
     verdad; en previsualizaciones no está, de ahí el @isset. --}}
@isset($message)
<img src="{{ $message->embed(public_path('images/logo_semahn.png')) }}"
     alt="Humanismo que Transforma — Secretaría de Medio Ambiente e Historia Natural"
     width="230"
     style="display:block; margin:0 auto; max-width:230px; height:auto;">
@else
<span style="font-family: Helvetica, Arial, sans-serif; font-size: 20px; font-weight: bold;
             letter-spacing: 2px; text-transform: uppercase; color: #009887;">
{{ $slot }}
</span>
@endisset
</a>
</td>
</tr>
