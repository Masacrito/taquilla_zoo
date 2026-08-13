<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AuthSeeder::class,             // roles, 7 permisos base y Super Admin
            PermisosTaquillaSeeder::class, // 14 permisos operativos (brief §3.3)
            CatalogosSeeder::class,        // países, estados, municipios, clasificación (brief §5.1)
        ]);
    }
}
