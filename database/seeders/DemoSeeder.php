<?php

namespace Database\Seeders;

use App\Models\AforoDiario;
use App\Models\Nacionalidad;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de PRUEBA para poder recorrer el flujo completo en desarrollo.
 *
 * ⚠️ NO se ejecuta con `migrate:fresh --seed`: hay que llamarlo a mano.
 *     php artisan db:seed --class=DemoSeeder
 *
 * ⚠️ LOS PRECIOS SON INVENTADOS. El brief no incluye el tarifario del ZooMAT.
 *     Antes de usar esto en serio, captura las tarifas reales desde
 *     /admin/rubros y desactiva o elimina estos rubros.
 *
 * Es idempotente: puede volver a ejecutarse sin duplicar.
 */
class DemoSeeder extends Seeder
{
    /** Días hacia adelante para los que se genera cupo. */
    private const DIAS_DE_AFORO = 60;

    public function run(): void
    {
        $this->rubrosDePrueba();
        $this->aforoDePrueba();

        $this->command?->warn('  Datos de PRUEBA cargados. Los precios son inventados: reemplázalos en /admin/rubros.');
    }

    private function rubrosDePrueba(): void
    {
        $nacional   = Nacionalidad::where('nombre', 'NACIONAL')->firstOrFail();
        $extranjero = Nacionalidad::where('nombre', 'EXTRANJERO')->firstOrFail();
        $pago       = TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->firstOrFail();
        $gratis     = TipoAcceso::where('nombre', TipoAcceso::GRATIS)->firstOrFail();

        $sub = fn (string $nombre) => Subnacionalidad::where('nombre', $nombre)->firstOrFail();

        $rubros = [
            ['Adulto nacional',    'Visitante mayor de edad con residencia en México.', $nacional,   $sub('ADULTO NACIONAL'),    $pago,   4000],
            ['Niño nacional',      'De 3 a 12 años. Menores de 1.20 m entran gratis.',  $nacional,   $sub('NIÑO NACIONAL'),      $pago,   2000],
            ['Adulto extranjero',  'Visitante mayor de edad sin residencia en México.', $extranjero, $sub('ADULTO EXTRANJERO'),  $pago,   8000],
            ['Niño extranjero',    'De 3 a 12 años, sin residencia en México.',         $extranjero, $sub('NIÑO EXTRANJERO'),    $pago,   4000],
            ['Niño Pavón',         'Menores de 1.20 m de estatura. Acceso sin costo.',  $nacional,   $sub('NIÑO NACIONAL'),      $gratis, 0],
        ];

        foreach ($rubros as [$tipo, $descripcion, $nacionalidad, $subnacionalidad, $tipoAcceso, $centavos]) {
            Rubro::updateOrCreate(
                ['tipo' => $tipo],
                [
                    'descripcion'        => $descripcion,
                    'id_nacionalidad'    => $nacionalidad->id,
                    'id_subnacionalidad' => $subnacionalidad->id,
                    'id_tipo_acceso'     => $tipoAcceso->id,
                    'precio_centavos'    => $centavos,
                    'vigente_desde'      => now()->startOfYear(),
                    'vigente_hasta'      => null,
                    'activo'             => true,
                ],
            );
        }
    }

    private function aforoDePrueba(): void
    {
        $cupo = AforoDiario::cupoPorOmision();

        for ($i = 0; $i < self::DIAS_DE_AFORO; $i++) {
            $dia   = Carbon::today()->addDays($i);
            $fecha = $dia->toDateString();

            if (AforoDiario::where('fecha', $fecha)->exists()) {
                continue;   // respeta lo que ya se haya ajustado a mano
            }

            $esLunes = AforoDiario::esLunes($dia);

            AforoDiario::create([
                'fecha'         => $fecha,
                'cupo_maximo'   => $cupo,
                'reservados'    => 0,
                'cerrado'       => $esLunes,
                'motivo_cierre' => $esLunes ? 'Lunes: el zoológico no abre.' : null,
            ]);
        }
    }
}
