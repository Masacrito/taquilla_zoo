<?php

namespace App\Services\Venta;

use App\Exceptions\DiaNoDisponibleException;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Services\Auditoria\BitacoraService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra una compra completa (brief §5.4, §4.5, §4.6).
 *
 * Todo ocurre dentro de UNA transacción:
 *   1. Folio consecutivo, tomando el contador con bloqueo de fila. Si el
 *      comprador ya tenía una compra sin pagar, se retoma esa con su folio.
 *   2. Cabecera de la compra en estado `pendiente_pago`.
 *   3. Detalle con el precio y el nombre del rubro CONGELADOS.
 *
 * Si algo falla, se revierte todo y no quedan folios huérfanos.
 *
 * No se reserva cupo: no hay aforo máximo. Lo único que se valida de la fecha
 * es que el día exista en el calendario y esté abierto.
 */
class RegistrarCompraService
{
    private const SERIE_FOLIO = 'ZM';

    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function registrar(
        Cliente $cliente,
        Cotizacion $cotizacion,
        string $fechaVisita,
        ?string $observaciones = null,
    ): Compra {
        return $this->crear($cliente->id, null, $cotizacion, $fechaVisita, $observaciones);
    }

    /**
     * Compra sin cuenta: el comprador es un correo ya verificado con código.
     *
     * Si ese correo pertenece a una cuenta, la compra queda ligada a ella y
     * le aparecerá en «Mis compras» cuando ingrese. Es legítimo porque el
     * código probó que quien compra controla el buzón; lo que NO se hace es
     * iniciarle sesión.
     */
    public function registrarInvitado(string $correo, Cotizacion $cotizacion, string $fechaVisita): Compra
    {
        $correo = mb_strtolower(trim($correo));

        return $this->crear(
            Cliente::where('correo', $correo)->value('id'),
            $correo,
            $cotizacion,
            $fechaVisita,
        );
    }

    private function crear(
        ?string $idCliente,
        ?string $correoInvitado,
        Cotizacion $cotizacion,
        string $fechaVisita,
        ?string $observaciones = null,
    ): Compra {
        return DB::transaction(function () use ($idCliente, $correoInvitado, $cotizacion, $fechaVisita, $observaciones) {

            // Lo único que se valida de la fecha es que el día exista en el
            // calendario y no esté cerrado. Sin cupo que revisar no hay nada
            // que reservar, ni carrera que se pueda perder.
            $dia = AforoDiario::where('fecha', $fechaVisita)->first();

            if (! $dia || $dia->cerrado) {
                throw DiaNoDisponibleException::paraFecha($fechaVisita);
            }

            // fecha_compra se renueva también al retomar: es el reloj de los
            // 15 minutos, y sin eso la compra expiraría a medio pago.
            $datos = [
                'fecha_compra'   => now(),
                'fecha_visita'   => $fechaVisita,
                'total_centavos' => $cotizacion->totalCentavos,
                'pases_total'    => $cotizacion->pasesTotal,
                'observaciones'  => $observaciones,
            ];

            // El contador de folios se bloquea ANTES de buscar la pendiente:
            // dos envíos simultáneos del mismo comprador (doble clic, dos
            // pestañas) se forman aquí, y el segundo ya encuentra la compra
            // del primero en vez de crear otra.
            $this->contadorBloqueado();

            $compra = $this->pendienteDe($idCliente, $correoInvitado);

            if ($compra) {
                $this->vaciar($compra);
                $compra->fill($datos);
                // Si la había archivado, volver a comprar la trae de regreso.
                $compra->archivada_en = null;
                $compra->save();
            } else {
                $compra = Compra::create([
                    ...$datos,
                    'folio'           => $this->siguienteFolio(),
                    'id_cliente'      => $idCliente,
                    'correo_invitado' => $correoInvitado,
                    'pases_usados'    => 0,
                    'estado'          => Compra::PENDIENTE_PAGO,
                ]);
            }

            foreach ($cotizacion->renglones as $renglon) {
                CompraDetalle::create([
                    'id_compra'            => $compra->id,
                    'id_rubro'             => $renglon->rubro->id,
                    // Congelados: cambiar la tarifa mañana no toca esta compra.
                    'rubro_nombre_snap'    => $renglon->nombre,
                    'precio_centavos_snap' => $renglon->precioCentavos,
                    'cant_hombre'          => $renglon->cantHombre,
                    'cant_mujer'           => $renglon->cantMujer,
                    'cantidad'             => $renglon->cantidad,
                    'importe_centavos'     => $renglon->importeCentavos,
                    'id_pais'              => $renglon->idPais,
                    'id_estado'            => $renglon->idEstado,
                    'id_municipio'         => $renglon->idMunicipio,
                    'id_nacionalidad'      => $renglon->rubro->id_nacionalidad,
                    'id_subnacionalidad'   => $renglon->rubro->id_subnacionalidad,
                    'id_tipo_acceso'       => $renglon->rubro->id_tipo_acceso,
                ]);
            }

            return $compra->load('detalle');
        });
    }

    /**
     * La compra sin pagar que el comprador dejó abierta (2026-10-11).
     *
     * Se retoma en vez de crear otra: quien regresa a corregir su carrito
     * antes de pagar conserva un solo registro y un solo folio, en lugar de
     * dejar un pendiente por cada intento.
     */
    private function pendienteDe(?string $idCliente, ?string $correoInvitado): ?Compra
    {
        return Compra::pendienteDe($idCliente, $correoInvitado)
            // Con bloqueo: el job de expiración no debe cerrarla a media edición.
            ->when(DB::connection()->getDriverName() !== 'sqlite', fn ($q) => $q->lockForUpdate())
            ->latest('id')
            ->first();
    }

    /**
     * Quita los renglones del carrito anterior para escribir los nuevos.
     *
     * Es el único borrado físico del módulo y solo toca una compra que nunca
     * se pagó. Lo que había queda en la bitácora (brief §4.6, §4.7).
     */
    private function vaciar(Compra $compra): void
    {
        $this->bitacora->registrar('compras', BitacoraService::UPDATE, (string) $compra->id, [
            'folio'    => $compra->folio,
            'motivo'   => 'carrito modificado antes de pagar',
            'anterior' => [
                'fecha_visita'   => $compra->fecha_visita->toDateString(),
                'total_centavos' => $compra->total_centavos,
                'renglones'      => $compra->detalle()
                    ->get(['id_rubro', 'rubro_nombre_snap', 'precio_centavos_snap', 'cant_hombre', 'cant_mujer'])
                    ->toArray(),
            ],
        ]);

        $compra->detalle()->delete();
    }

    /**
     * Folio consecutivo sin huecos (brief §4.6).
     *
     * Se bloquea la fila del contador con SELECT ... FOR UPDATE: las compras
     * concurrentes se serializan aquí. Si la transacción se revierte, el
     * número no se consume — que es exactamente lo que evita los huecos.
     *
     * Una secuencia de PostgreSQL NO serviría: no se revierte en rollback.
     */
    private function siguienteFolio(): string
    {
        $serie = self::SERIE_FOLIO;

        $fila = $this->contadorBloqueado();

        if (! $fila) {
            DB::table('folios')->insert([
                'serie'      => $serie,
                'ultimo'     => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ultimo = 0;
        } else {
            $ultimo = (int) $fila->ultimo;
        }

        $siguiente = $ultimo + 1;

        DB::table('folios')->where('serie', $serie)->update([
            'ultimo'     => $siguiente,
            'updated_at' => now(),
        ]);

        return sprintf('%s-%s-%06d', $serie, Carbon::now()->format('Y'), $siguiente);
    }

    /**
     * La fila del contador, tomada con bloqueo hasta que termine la
     * transacción. Null solo antes de la primera compra del sistema.
     */
    private function contadorBloqueado(): ?object
    {
        $consulta = DB::table('folios')->where('serie', self::SERIE_FOLIO);

        // lockForUpdate no existe en SQLite; ahí la transacción ya serializa.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $consulta->lockForUpdate();
        }

        return $consulta->first();
    }
}
