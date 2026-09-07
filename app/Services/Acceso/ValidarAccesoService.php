<?php

namespace App\Services\Acceso;

use App\Models\Acceso;
use App\Models\Compra;
use App\Models\Cuenta;
use Illuminate\Support\Carbon;

/**
 * Valida pases en el acceso (brief §4.5, §5.6).
 *
 * ═══════════════════════════════════════════════════════════════════════
 *  DOS PASOS: consultar → confirmar.
 *
 *  `consultar()` revisa todo pero NO consume nada. `validar()` es el que
 *  descuenta. Separarlos importa porque un QR puede traer varios pases: el
 *  operador necesita ver cuántos quedan y decidir cuántos entran, en vez de
 *  que la cámara consuma a ciegas con solo enfocar el código.
 *
 *  El descuento sigue siendo ATÓMICO: Compra::consumirPases() ejecuta una
 *  sola sentencia condicional. Con dos torniquetes leyendo el mismo QR a la
 *  vez, un SELECT + UPDATE dejaría entrar al doble de gente.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * El código de entrada puede ser el TOKEN del QR (con firma HMAC) o el
 * FOLIO capturado a mano cuando la cámara falla. El folio no lleva firma,
 * así que se apoya en que el operador esté autenticado y con permiso; por
 * eso queda asentado con `metodo = folio`, distinguible en la auditoría.
 */
class ValidarAccesoService
{
    public function __construct(private readonly QrTokenService $qr)
    {
    }

    /**
     * Revisa el pase sin consumir nada. Los rechazos SÍ se asientan: un
     * código falsificado presentado en la puerta es justo lo que hay que
     * poder investigar después.
     */
    public function consultar(
        string $codigo,
        string $idTorniquete = 'web',
        ?Cuenta $operador = null,
        ?string $fechaReferencia = null,
    ): ResultadoAcceso {
        [$rechazo, $compra, $metodo] = $this->revisar($codigo, $fechaReferencia);

        if ($rechazo !== null) {
            return $this->asentar($rechazo, $idTorniquete, $metodo, $operador);
        }

        // Consulta exitosa: no se asienta. La entrada real se registra en
        // validar(); asentar también la consulta duplicaría cada visitante.
        return ResultadoAcceso::consultable($compra);
    }

    /**
     * Confirma la entrada y descuenta los pases.
     */
    public function validar(
        string $codigo,
        int $pases,
        string $idTorniquete = 'web',
        ?Cuenta $operador = null,
        ?string $fechaReferencia = null,
    ): ResultadoAcceso {
        $pases = max(1, $pases);

        [$rechazo, $compra, $metodo] = $this->revisar($codigo, $fechaReferencia);

        if ($rechazo !== null) {
            return $this->asentar($rechazo, $idTorniquete, $metodo, $operador);
        }

        // Descuento atómico. Si devuelve false, otro escaneo se adelantó o
        // se pidieron más pases de los que quedan.
        if (! Compra::consumirPases($compra->id, $pases)) {
            return $this->asentar(
                ResultadoAcceso::rechazado(
                    ResultadoAcceso::SIN_PASES,
                    'No quedan suficientes pases en este código.',
                    $compra,
                ),
                $idTorniquete,
                $metodo,
                $operador,
            );
        }

        $compra->refresh();

        $compra->estado = $compra->pasesDisponibles() === 0
            ? Compra::UTILIZADA
            : Compra::ACCESO_PARCIAL;
        $compra->save();

        return $this->asentar(
            ResultadoAcceso::permitido($compra, $pases),
            $idTorniquete,
            $metodo,
            $operador,
        );
    }

    /**
     * Todas las comprobaciones, sin tocar los pases.
     *
     * @return array{0: ?ResultadoAcceso, 1: ?Compra, 2: string}
     *         [rechazo o null, compra o null, método de identificación]
     */
    private function revisar(string $codigo, ?string $fechaReferencia): array
    {
        $codigo = trim($codigo);
        $hoy    = $fechaReferencia ?? Carbon::today()->toDateString();
        $metodo = $this->pareceToken($codigo) ? Acceso::METODO_QR : Acceso::METODO_FOLIO;

        $compra = $metodo === Acceso::METODO_QR
            ? $this->porToken($codigo)
            : Compra::where('folio', mb_strtoupper($codigo))->first();

        if (! $compra) {
            return [
                ResultadoAcceso::rechazado(
                    $metodo === Acceso::METODO_QR
                        ? ResultadoAcceso::TOKEN_INVALIDO
                        : ResultadoAcceso::NO_ENCONTRADA,
                    $metodo === Acceso::METODO_QR
                        ? 'Código no válido o alterado.'
                        : 'No existe ninguna compra con ese folio.',
                ),
                null,
                $metodo,
            ];
        }

        // Solo aplica al QR: si la compra se reagendó se emitió un token
        // nuevo, y el impreso viejo ya no sirve.
        if ($metodo === Acceso::METODO_QR && $compra->qr_token !== $codigo) {
            return [
                ResultadoAcceso::rechazado(
                    ResultadoAcceso::QR_REEMPLAZADO,
                    'Este código fue reemplazado. Pide el más reciente.',
                    $compra,
                ),
                null,
                $metodo,
            ];
        }

        if (in_array($compra->estado, [Compra::CANCELADA, Compra::REEMBOLSADA], true)) {
            return [
                ResultadoAcceso::rechazado(ResultadoAcceso::CANCELADA, 'Esta compra fue cancelada.', $compra),
                null,
                $metodo,
            ];
        }

        // Va antes del cheque de "pagada": una compra utilizada SÍ se pagó, y
        // decirle al operador que no está pagada lo mandaría a investigar el
        // problema equivocado.
        if ($compra->estado === Compra::UTILIZADA) {
            return [
                ResultadoAcceso::rechazado(
                    ResultadoAcceso::SIN_PASES,
                    'Este código ya fue utilizado por completo.',
                    $compra,
                ),
                null,
                $metodo,
            ];
        }

        if (! $compra->estaPagada()) {
            return [
                ResultadoAcceso::rechazado(
                    ResultadoAcceso::NO_PAGADA,
                    "Esta compra no está pagada (estado: {$compra->estado}).",
                    $compra,
                ),
                null,
                $metodo,
            ];
        }

        // Sin tolerancia de fechas: solo el día programado (brief §12).
        if ($compra->fecha_visita->toDateString() !== $hoy) {
            return [
                ResultadoAcceso::rechazado(
                    ResultadoAcceso::FECHA_DISTINTA,
                    'Este código es para el ' . $compra->fecha_visita->format('d/m/Y') . '.',
                    $compra,
                ),
                null,
                $metodo,
            ];
        }

        return [null, $compra, $metodo];
    }

    /**
     * Un token tiene la forma id.folio.fecha.firma. Un folio no lleva puntos,
     * así que la forma basta para distinguirlos.
     */
    private function pareceToken(string $codigo): bool
    {
        return substr_count($codigo, '.') === 3;
    }

    private function porToken(string $token): ?Compra
    {
        $datos = $this->qr->verificar($token);

        if ($datos === null) {
            return null;
        }

        $compra = Compra::find($datos['compra_id']);

        return ($compra && $compra->folio === $datos['folio']) ? $compra : null;
    }

    private function asentar(
        ResultadoAcceso $resultado,
        string $idTorniquete,
        string $metodo,
        ?Cuenta $operador,
    ): ResultadoAcceso {
        Acceso::create([
            'id_compra'        => $resultado->compra?->id,
            'id_torniquete'    => $idTorniquete,
            'metodo'           => $metodo,
            'pases_consumidos' => $resultado->pasesConsumidos,
            'escaneado_en'     => now(),
            'id_cuenta'        => $operador?->id_cuenta,
            'resultado'        => $resultado->permitido ? Acceso::PERMITIDO : Acceso::RECHAZADO,
            'motivo_rechazo'   => $resultado->motivo,
        ]);

        return $resultado;
    }
}
