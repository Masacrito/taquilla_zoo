<?php

namespace App\Services\Acceso;

use App\Models\Compra;
use Illuminate\Support\Facades\Config;

/**
 * Firma y verificación del token del QR (brief §6).
 *
 * HMAC-SHA256 sobre {compra_id}.{folio}.{fecha_visita} con APP_KEY.
 *
 * La gracia es que un token alterado se rechaza SIN consultar la base: el
 * torniquete puede descartar basura antes de gastar una query, y en modo
 * contingencia puede validar la firma sin conexión.
 *
 * La imagen del QR se genera al vuelo, nunca se almacena.
 */
class QrTokenService
{
    private const SEPARADOR = '.';

    public function generar(Compra $compra): string
    {
        $carga = $this->carga($compra->id, $compra->folio, $compra->fecha_visita->toDateString());

        return $carga . self::SEPARADOR . $this->firmar($carga);
    }

    /**
     * Verifica la firma y devuelve los datos embebidos, o null si el token
     * está mal formado o fue alterado. No toca la base de datos.
     *
     * @return array{compra_id:int, folio:string, fecha_visita:string}|null
     */
    public function verificar(string $token): ?array
    {
        $partes = explode(self::SEPARADOR, $token);

        if (count($partes) !== 4) {
            return null;
        }

        [$compraId, $folio, $fechaVisita, $firma] = $partes;

        $carga = $this->carga($compraId, $folio, $fechaVisita);

        // hash_equals: comparación en tiempo constante, para no filtrar
        // información por el tiempo de respuesta.
        if (! hash_equals($this->firmar($carga), $firma)) {
            return null;
        }

        return [
            'compra_id'    => (int) $compraId,
            'folio'        => $folio,
            'fecha_visita' => $fechaVisita,
        ];
    }

    private function carga(int|string $compraId, string $folio, string $fechaVisita): string
    {
        return implode(self::SEPARADOR, [$compraId, $folio, $fechaVisita]);
    }

    private function firmar(string $carga): string
    {
        return hash_hmac('sha256', $carga, $this->llave());
    }

    private function llave(): string
    {
        $llave = Config::get('app.key');

        if (blank($llave)) {
            throw new \RuntimeException('APP_KEY no está definida: no se puede firmar el QR.');
        }

        // APP_KEY viene en base64:... — se usa el binario real.
        if (str_starts_with($llave, 'base64:')) {
            $llave = base64_decode(substr($llave, 7));
        }

        return $llave;
    }
}
