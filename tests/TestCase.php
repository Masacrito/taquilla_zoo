<?php

namespace Tests;

use App\Models\Pais;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Una procedencia válida y mínima para los formularios que la exigen
     * (registro y compra): un país que no es México no pide estado ni
     * municipio. Requiere CatalogosSeeder.
     */
    protected function procedencia(): array
    {
        return ['id_pais' => Pais::where('iso', 'USA')->value('id')];
    }
}
