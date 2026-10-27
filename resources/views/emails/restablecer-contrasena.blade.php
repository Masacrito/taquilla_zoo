<x-mail::message>
# Restablece tu contraseña

Recibimos una solicitud para cambiar la contraseña de tu cuenta en el portal del ZooMAT.

<x-mail::button :url="$url" color="success">
Elegir contraseña nueva
</x-mail::button>

El enlace vence en **{{ $minutos }} minutos**.

Si no fuiste tú, ignora este mensaje: tu contraseña no cambia.

**Zoológico Regional Miguel Álvarez del Toro**

<x-slot:subcopy>
Secretaría de Medio Ambiente e Historia Natural · Gobierno del Estado de Chiapas.
</x-slot:subcopy>
</x-mail::message>
