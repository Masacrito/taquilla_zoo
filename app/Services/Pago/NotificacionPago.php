<?php

namespace App\Services\Pago;

/**
 * Webhook ya verificado y normalizado.
 *
 * `firmaValida` la resuelve el adaptador de cada proveedor; el servicio de
 * confirmación no vuelve a interpretarla, solo la respeta.
 */
final class NotificacionPago
{
    public const APROBADO  = 'aprobado';
    public const RECHAZADO = 'rechazado';

    /**
     * El evento es legítimo pero no nos corresponde.
     *
     * Las pasarelas mandan varios eventos por cobro y a nosotros solo nos
     * importa el que confirma o rechaza el pago. Sin este estado, cualquier
     * evento no reconocido caía en la rama de «no aprobado» y quedaba
     * escrito como un pago RECHAZADO que nunca ocurrió, ensuciando la
     * conciliación y los cortes.
     */
    public const IGNORADA = 'ignorada';

    public function __construct(
        public readonly bool $firmaValida,
        public readonly string $referenciaExterna,
        public readonly string $estado,
        public readonly int $montoCentavos,
        public readonly ?string $autorizacion,
        public readonly array $payload,
    ) {
    }

    /**
     * Evento que no nos toca procesar.
     *
     * Se marca con `firmaValida: true` a propósito: el adaptador solo debe
     * construirla DESPUÉS de haber verificado la firma. Si la firma no
     * cuadra, lo que corresponde es una notificación normal con
     * `firmaValida: false`, para que el webhook responda 400.
     */
    public static function ignorada(array $payload = []): self
    {
        return new self(
            firmaValida:       true,
            referenciaExterna: '',
            estado:            self::IGNORADA,
            montoCentavos:     0,
            autorizacion:      null,
            payload:           $payload,
        );
    }

    public function esIgnorable(): bool
    {
        return $this->estado === self::IGNORADA;
    }

    public function aprobado(): bool
    {
        return $this->estado === self::APROBADO;
    }
}
