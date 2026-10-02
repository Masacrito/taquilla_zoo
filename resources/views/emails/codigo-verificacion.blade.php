<x-mail::message>
# Verifica tu correo

@if ($proposito === \App\Models\VerificacionCorreo::INVITADO)
Usa este código para continuar con tu compra de boletos del ZooMAT. A este
correo te enviaremos tus boletos:
@else
Usa este código para terminar de crear tu cuenta en el portal del ZooMAT:
@endif

<div style="text-align:center; margin:28px 0;">
  <span style="display:inline-block; padding:14px 28px; background:#e6f7f5; color:#00524a;
               font-size:32px; letter-spacing:10px; font-weight:bold; border-radius:10px;">
    {{ $codigo }}
  </span>
</div>

El código vence en **{{ $minutosVigencia }} minutos**.

Si no fuiste tú, ignora este mensaje: sin el código nadie puede usar tu correo.

**Zoológico Regional Miguel Álvarez del Toro**

<x-slot:subcopy>
Secretaría de Medio Ambiente e Historia Natural · Gobierno del Estado de Chiapas.
</x-slot:subcopy>
</x-mail::message>
