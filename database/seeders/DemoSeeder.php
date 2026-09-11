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
 * Tarifas vigentes y calendario, para dejar el sistema listo para vender.
 *
 * NO se ejecuta con `migrate:fresh --seed`: hay que llamarlo a mano.
 *     php artisan db:seed --class=DemoSeeder
 *
 * Los precios de aquí SÍ son los reales. Si cambian, se capturan desde
 * /admin/rubros —que además deja rastro en la bitácora— y este archivo se
 * actualiza para que un despliegue nuevo arranque con las correctas.
 *
 * Es idempotente: puede volver a ejecutarse sin duplicar.
 */
class DemoSeeder extends Seeder
{
    /** Días hacia adelante que se abren en el calendario. */
    private const DIAS_DE_CALENDARIO = 60;

    public function run(): void
    {
        $this->tarifas();
        $this->calendarioDePrueba();

        $this->command?->info('  Tarifas y calendario cargados.');
    }

    private function tarifas(): void
    {
        $nacional = Nacionalidad::where('nombre', 'NACIONAL')->firstOrFail();
        $pago     = TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->firstOrFail();

        // Sin usar mientras los rubros de extranjero y Niño Pavón sigan
        // comentados abajo. Se dejan para que reactivarlos sea descomentar
        // una línea y nada más.
        $extranjero = Nacionalidad::where('nombre', 'EXTRANJERO')->firstOrFail();
        $gratis     = TipoAcceso::where('nombre', TipoAcceso::GRATIS)->firstOrFail();

        $sub = fn (string $nombre) => Subnacionalidad::where('nombre', $nombre)->firstOrFail();

        // Tarifas vigentes, en centavos enteros (brief §4.1).
        $rubros = [
            ['Adulto',       'Visitante mayor de edad.',                          $nacional, $sub('ADULTO NACIONAL'),       $pago, 3500],
            ['Niños',        'De 3 a 12 años.',                                   $nacional, $sub('NIÑO NACIONAL'),         $pago, 3500],
            ['Tercera Edad', 'Adultos mayores. Se presenta credencial INAPAM.',   $nacional, $sub('TERCERA EDAD NACIONAL'), $pago, 2500],

            // Estos operaban antes y por ahora no se cobran. Se dejan a la
            // vista para reactivarlos, pero COMENTADOS a propósito: sus
            // precios nunca se confirmaron con el área operativa y sembrar
            // cifras inventadas es peor que no tenerlas.
            //
            // ['Adulto extranjero', 'Visitante mayor de edad sin residencia en México.', $extranjero, $sub('ADULTO EXTRANJERO'), $pago,   0],
            // ['Niño extranjero',   'De 3 a 12 años, sin residencia en México.',         $extranjero, $sub('NIÑO EXTRANJERO'),   $pago,   0],
            // ['Niño Pavón',        'Menores de 1.20 m de estatura. Acceso sin costo.',  $nacional,   $sub('NIÑO NACIONAL'),     $gratis, 0],
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

    private function calendarioDePrueba(): void
    {
        for ($i = 0; $i < self::DIAS_DE_CALENDARIO; $i++) {
            $dia   = Carbon::today()->addDays($i);
            $fecha = $dia->toDateString();

            if (AforoDiario::where('fecha', $fecha)->exists()) {
                continue;   // respeta lo que ya se haya ajustado a mano
            }

            $esLunes = AforoDiario::esLunes($dia);

            AforoDiario::create([
                'fecha'         => $fecha,
                'cerrado'       => $esLunes,
                'motivo_cierre' => $esLunes ? 'Lunes: el zoológico no abre.' : null,
            ]);
        }
    }
}
