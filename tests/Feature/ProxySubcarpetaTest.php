<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * En el VPS, Caddy publica la taquilla en https://semahnchiapas.com.mx/taquilla,
 * quita "/taquilla" y habla http con nginx. Laravel tiene que reconstruir la
 * URL pública con las cabeceras X-Forwarded-*, o los enlaces firmados del
 * correo de invitado dan 403 y las URL generadas pierden https y el prefijo.
 */
class ProxySubcarpetaTest extends TestCase
{
    private const CADDY = ['REMOTE_ADDR' => '172.18.0.5'];

    private const CABECERAS = [
        'X-Forwarded-For'    => '201.141.10.20',
        'X-Forwarded-Proto'  => 'https',
        'X-Forwarded-Host'   => 'semahnchiapas.com.mx',
        'X-Forwarded-Prefix' => '/taquilla',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('signed')->get('/_prueba-firma', fn () => 'ok')->name('prueba.firma');
        Route::get('/_prueba-eco', fn () => [request()->ip(), route('prueba.firma')]);
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_el_enlace_firmado_del_correo_valida_detras_del_proxy(): void
    {
        // El worker de colas arma el enlace con APP_URL, que ya trae /taquilla.
        URL::forceRootUrl('https://semahnchiapas.com.mx/taquilla');
        URL::forceScheme('https');
        $enlace = URL::signedRoute('prueba.firma');
        URL::forceRootUrl(null);
        URL::forceScheme(null);

        $publica = 'https://semahnchiapas.com.mx/taquilla';
        $this->assertStringStartsWith("{$publica}/_prueba-firma?", $enlace);

        // Lo que llega a nginx: Caddy ya quitó /taquilla y habla http.
        $interna = 'http://semahnchiapas.com.mx'.substr($enlace, strlen($publica));

        $this->withServerVariables(self::CADDY)
            ->withHeaders(self::CABECERAS)
            ->get($interna)
            ->assertOk();
    }

    public function test_detras_del_proxy_genera_urls_publicas_y_ve_la_ip_real(): void
    {
        $this->withServerVariables(self::CADDY)
            ->withHeaders(self::CABECERAS)
            ->get('http://semahnchiapas.com.mx/_prueba-eco')
            ->assertExactJson(['201.141.10.20', 'https://semahnchiapas.com.mx/taquilla/_prueba-firma']);
    }

    public function test_un_cliente_de_internet_no_puede_falsear_las_cabeceras(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '201.141.10.20'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4'] + self::CABECERAS)
            ->get('http://semahnchiapas.com.mx/_prueba-eco')
            ->assertExactJson(['201.141.10.20', 'http://semahnchiapas.com.mx/_prueba-firma']);
    }
}
