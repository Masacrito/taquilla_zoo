<?php

namespace App\Services\Acceso;

use App\Models\Compra;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Imagen del QR (brief §6).
 *
 * Se genera AL VUELO en cada petición; nunca se almacena. Lo único
 * persistido es el token firmado, del que la imagen es solo una
 * representación.
 *
 * Se usa el escritor SVG y no PNG a propósito: no depende de las extensiones
 * GD ni Imagick, escala sin pixelarse en la pantalla del torniquete, y pesa
 * menos que un PNG equivalente.
 *
 * Corrección de errores alta (H): el QR sigue siendo legible aunque la
 * pantalla del visitante esté rayada, sucia o con brillo.
 */
class QrImagenService
{
    public function svg(Compra $compra, int $tamano = 320): string
    {
        if (blank($compra->qr_token)) {
            throw new \RuntimeException("La compra {$compra->folio} no tiene QR emitido.");
        }

        return $this->svgDeTexto($compra->qr_token, $tamano);
    }

    /**
     * PNG en data URI, para incrustar en el PDF del comprobante.
     *
     * dompdf no rasteriza SVG de forma confiable, así que aquí sí se usa el
     * escritor PNG (requiere la extensión GD).
     */
    public function pngDataUri(Compra $compra, int $tamano = 400): string
    {
        return $this->constructorPng($compra, $tamano)->getDataUri();
    }

    /**
     * PNG en binario, para incrustarlo en el correo como adjunto en línea
     * (CID).
     *
     * En el correo NO se puede usar SVG: Gmail y la mayoría de los clientes
     * lo eliminan por seguridad, y el visitante recibe el mensaje sin su
     * código. Tampoco sirve un data URI, que Gmail también bloquea. La única
     * vía confiable es un PNG adjunto y referenciado con cid:.
     */
    public function pngBinario(Compra $compra, int $tamano = 400): string
    {
        return $this->constructorPng($compra, $tamano)->getString();
    }

    private function constructorPng(Compra $compra, int $tamano): \Endroid\QrCode\Writer\Result\ResultInterface
    {
        if (blank($compra->qr_token)) {
            throw new \RuntimeException("La compra {$compra->folio} no tiene QR emitido.");
        }

        return (new Builder(
            writer: new PngWriter(),
            data: $compra->qr_token,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $tamano,
            margin: 12,
            foregroundColor: new Color(0, 82, 74),
            backgroundColor: new Color(255, 255, 255),
        ))->build();
    }

    public function svgDeTexto(string $contenido, int $tamano = 320): string
    {
        $resultado = (new Builder(
            writer: new SvgWriter(),
            data: $contenido,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $tamano,
            margin: 12,
            // Jade del manual gráfico sobre blanco: contraste suficiente para
            // que cualquier lector lo distinga.
            foregroundColor: new Color(0, 82, 74),
            backgroundColor: new Color(255, 255, 255),
        ))->build();

        return $resultado->getString();
    }
}
