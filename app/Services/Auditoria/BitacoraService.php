<?php

namespace App\Services\Auditoria;

use App\Models\Movimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Único punto de escritura de la bitácora (brief §4.7).
 *
 * `movimientos` es de SOLO INSERCIÓN: nada la actualiza ni la borra. Toda
 * operación sensible sobre catálogos, rubros, precios, cuentas, calendario,
 * cancelaciones, reembolsos y accesos pasa por aquí.
 */
class BitacoraService
{
    public const CREATE = 'CREATE';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';

    /**
     * Campos que nunca deben quedar escritos en la bitácora.
     */
    private const OCULTOS = ['password', 'remember_token', 'password_confirmation', '_token', '_method'];

    public function registrar(
        string $tabla,
        string $accion,
        ?string $registroId = null,
        ?array $detalles = null,
    ): Movimiento {
        return Movimiento::registrar(
            $this->cuentaResponsable(),
            $tabla,
            $accion,
            $registroId,
            $detalles === null ? null : $this->depurar($detalles),
        );
    }

    public function creado(Model $modelo, ?array $detalles = null): Movimiento
    {
        return $this->registrar(
            $modelo->getTable(),
            self::CREATE,
            (string) $modelo->getKey(),
            $detalles ?? $modelo->getAttributes(),
        );
    }

    /**
     * Registra solo lo que cambió, con valor anterior y nuevo. Si no cambió
     * nada, no ensucia la bitácora.
     */
    public function actualizado(Model $modelo, array $anterior): ?Movimiento
    {
        $cambios = [];

        foreach ($modelo->getAttributes() as $campo => $nuevo) {
            $previo = $anterior[$campo] ?? null;

            if ((string) $previo !== (string) $nuevo) {
                $cambios[$campo] = ['antes' => $previo, 'despues' => $nuevo];
            }
        }

        unset($cambios['updated_at']);

        if ($cambios === []) {
            return null;
        }

        return $this->registrar(
            $modelo->getTable(),
            self::UPDATE,
            (string) $modelo->getKey(),
            $cambios,
        );
    }

    public function eliminado(Model $modelo, ?array $detalles = null): Movimiento
    {
        return $this->registrar(
            $modelo->getTable(),
            self::DELETE,
            (string) $modelo->getKey(),
            $detalles ?? $modelo->getAttributes(),
        );
    }

    /**
     * Cuenta interna responsable. Null cuando la acción la dispara el sistema
     * (jobs, expiración de compras) o un cliente del portal público, que no
     * pertenece al guard `web`.
     */
    private function cuentaResponsable(): ?int
    {
        $cuenta = Auth::guard('web')->user();

        return $cuenta?->id_cuenta;
    }

    private function depurar(array $detalles): array
    {
        foreach (self::OCULTOS as $campo) {
            if (array_key_exists($campo, $detalles)) {
                $detalles[$campo] = '[oculto]';
            }
        }

        return $detalles;
    }
}
