<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Permisos operativos del sistema de taquilla (brief §3.3).
 *
 * No toca AuthSeeder ni los 7 permisos base de administración de cuentas
 * (gestion_usuarios, crear_usuarios, editar_usuarios, eliminar_usuarios,
 * cambiar_roles, activar_cuentas, gestion_permisos), que siguen siendo
 * exclusivos de Administrador.
 *
 * Es idempotente: se puede volver a ejecutar sin duplicar nada.
 */
class PermisosTaquillaSeeder extends Seeder
{
    /**
     * permiso => roles que lo reciben.
     */
    private const MATRIZ = [
        'gestion_rubros'         => ['descripcion' => 'Ver el catálogo de rubros de cobro',        'roles' => ['Administrador']],
        'editar_rubros'          => ['descripcion' => 'Crear y editar rubros y sus precios',       'roles' => ['Administrador']],
        'gestion_catalogos'      => ['descripcion' => 'Ver los catálogos del sistema',             'roles' => ['Administrador']],
        'editar_catalogos'       => ['descripcion' => 'Crear y editar registros de catálogos',     'roles' => ['Administrador']],
        'gestion_aforo'          => ['descripcion' => 'Definir días de apertura y cierres',       'roles' => ['Administrador']],
        'gestion_clientes'       => ['descripcion' => 'Consultar clientes y sus compras',          'roles' => ['Administrador']],
        'validar_accesos'        => ['descripcion' => 'Escanear QR y descontar pases en acceso',   'roles' => ['Administrador', 'Taquilla']],
        'ver_bitacora_accesos'   => ['descripcion' => 'Consultar la bitácora de accesos',          'roles' => ['Administrador', 'Taquilla']],
        'generar_cortes'         => ['descripcion' => 'Generar cortes de ingresos',                'roles' => ['Administrador', 'Taquilla']],
        'conciliar_pagos'        => ['descripcion' => 'Conciliar pagos contra la pasarela',        'roles' => ['Administrador']],
        'ver_estadisticas'       => ['descripcion' => 'Consultar estadísticas de visitantes',      'roles' => ['Administrador']],
        'cancelar_compras'       => ['descripcion' => 'Cancelar compras pagadas',                  'roles' => ['Administrador']],
        'autorizar_reembolsos'   => ['descripcion' => 'Autorizar solicitudes de reembolso',        'roles' => ['Administrador']],
        'ver_bitacora_auditoria' => ['descripcion' => 'Consultar la bitácora de auditoría',        'roles' => ['Administrador']],

        // Fuera de los 14 del brief §3.3. Se agregó con el registro de fallos
        // del sistema, que necesita su propia puerta: la bitácora de
        // auditoría y los errores son cosas distintas y no deben compartir
        // permiso.
        'ver_errores'            => ['descripcion' => 'Consultar los fallos del sistema',          'roles' => ['Administrador']],
    ];

    public function run(): void
    {
        $rolesPorNombre = DB::table('roles')->pluck('id_rol', 'nombre');

        foreach (self::MATRIZ as $nombre => $config) {
            $idPermiso = DB::table('permisos')->where('nombre', $nombre)->value('id_permiso');

            if ($idPermiso === null) {
                $idPermiso = DB::table('permisos')->insertGetId([
                    'nombre'      => $nombre,
                    'descripcion' => $config['descripcion'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ], 'id_permiso');
            }

            foreach ($config['roles'] as $nombreRol) {
                $idRol = $rolesPorNombre[$nombreRol] ?? null;

                if ($idRol === null) {
                    $this->command?->warn("Rol «{$nombreRol}» no existe; se omite el permiso «{$nombre}».");
                    continue;
                }

                $yaAsignado = DB::table('rol_permiso')
                    ->where('id_rol', $idRol)
                    ->where('id_permiso', $idPermiso)
                    ->exists();

                if (! $yaAsignado) {
                    DB::table('rol_permiso')->insert([
                        'id_rol'     => $idRol,
                        'id_permiso' => $idPermiso,
                    ]);
                }
            }
        }
    }
}
