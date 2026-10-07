<?php

namespace Tests\Feature;

use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Nacionalidad;
use App\Models\Pais;
use App\Models\Rubro;
use App\Models\Subnacionalidad;
use App\Models\TipoAcceso;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Dos reglas del 2026-10-05 sobre el portal:
 *
 *   - Pasada la hora de corte ya no se venden boletos para el mismo día.
 *   - La procedencia es obligatoria en el registro y en la compra, hasta
 *     donde el catálogo alcanza (país → estado si es México → municipio si
 *     el estado los tiene).
 *
 * El reloj va congelado en un miércoles: las dos reglas dependen de la hora.
 */
class CorteYProcedenciaTest extends TestCase
{
    use RefreshDatabase;

    private const HOY    = '2026-10-14';
    private const MANANA = '2026-10-15';

    private Rubro $rubro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->seed(CatalogosSeeder::class);

        Carbon::setTestNow(self::HOY . ' 10:00:00');
        Mail::fake();

        $this->rubro = Rubro::create([
            'tipo'               => 'Adulto nacional',
            'id_nacionalidad'    => Nacionalidad::where('nombre', 'NACIONAL')->value('id'),
            'id_subnacionalidad' => Subnacionalidad::where('nombre', 'ADULTO NACIONAL')->value('id'),
            'id_tipo_acceso'     => TipoAcceso::where('nombre', TipoAcceso::PAGO_NORMAL)->value('id'),
            'precio_centavos'    => 4000,
            'vigente_desde'      => now()->subMonth(),
            'activo'             => true,
        ]);

        AforoDiario::create(['fecha' => self::HOY]);
        AforoDiario::create(['fecha' => self::MANANA]);
    }

    private function cliente(array $extra = []): Cliente
    {
        return Cliente::create([
            'correo'               => 'ana@example.com',
            'nombre'               => 'Ana',
            'apellidos'            => 'Pérez',
            'fecha_nacimiento'     => '1990-01-01',
            'genero'               => 'Mujer',
            'telefono'             => '9610000000',
            'correo_verificado_en' => now(),
            ...$extra,
        ]);
    }

    private function carrito(string $fecha, array $procedencia): array
    {
        return [
            'fecha_visita' => $fecha,
            'renglones'    => [['id_rubro' => $this->rubro->id, 'cant_hombre' => 1, 'cant_mujer' => 1, ...$procedencia]],
        ];
    }

    private function registro(array $procedencia): array
    {
        return [
            'correo'                => 'nueva@example.com',
            'password'              => 'secreto12345',
            'password_confirmation' => 'secreto12345',
            'nombre'                => 'Nueva',
            'apellidos'             => 'Visitante',
            'fecha_nacimiento'      => '1995-03-10',
            'genero'                => 'Mujer',
            'telefono'              => '9610001111',
            ...$procedencia,
        ];
    }

    private function mexico(): int
    {
        return Pais::where('iso', Pais::ISO_MEXICO)->value('id');
    }

    private function chiapas(): int
    {
        return Estado::where('nombre', 'Chiapas')->value('id');
    }

    // ═══ Hora de corte ═══

    public function test_antes_del_corte_se_vende_para_hoy(): void
    {
        $this->actingAs($this->cliente(), 'cliente');

        $this->get('/comprar')->assertOk()->assertSee('data-dia="' . self::HOY . '"', false);

        $this->post('/comprar', $this->carrito(self::HOY, $this->procedencia()))->assertSessionHasNoErrors();
        $this->assertSame(1, Compra::count());
    }

    public function test_despues_del_corte_hoy_ya_no_se_vende_y_se_marca_el_siguiente_dia(): void
    {
        Carbon::setTestNow(self::HOY . ' ' . config('taquilla.compra.hora_corte_mismo_dia') . ':00');
        $this->actingAs($this->cliente(), 'cliente');

        $this->get('/comprar')
            ->assertOk()
            ->assertDontSee('data-dia="' . self::HOY . '"', false)
            ->assertSee('data-dia="' . self::MANANA . '"', false)
            // El siguiente día abierto llega ya elegido.
            ->assertViewHas('fechaElegida', self::MANANA);

        // Aunque el formulario se haya quedado abierto desde antes del corte.
        $this->post('/comprar', $this->carrito(self::HOY, $this->procedencia()))
            ->assertSessionHasErrors('fecha_visita');
        $this->assertSame(0, Compra::count());

        $this->post('/comprar', $this->carrito(self::MANANA, $this->procedencia()))->assertSessionHasNoErrors();
        $this->assertSame(1, Compra::count());
    }

    // ═══ Procedencia en la compra ═══

    public function test_no_se_compra_sin_procedencia(): void
    {
        $this->actingAs($this->cliente(), 'cliente');

        $this->post('/comprar', $this->carrito(self::MANANA, []))
            ->assertSessionHasErrors('renglones.0.id_pais');
        $this->post('/comprar', $this->carrito(self::MANANA, ['id_pais' => $this->mexico()]))
            ->assertSessionHasErrors('renglones.0.id_estado');
        $this->post('/comprar', $this->carrito(self::MANANA, ['id_pais' => $this->mexico(), 'id_estado' => $this->chiapas()]))
            ->assertSessionHasErrors('renglones.0.id_municipio');

        $this->assertSame(0, Compra::count());
    }

    public function test_la_compra_guarda_solo_la_procedencia_que_aplica(): void
    {
        $this->actingAs($this->cliente(), 'cliente');

        // Un país extranjero con estado y municipio colados: se descartan.
        $this->post('/comprar', $this->carrito(self::MANANA, [
            ...$this->procedencia(),
            'id_estado'    => $this->chiapas(),
            'id_municipio' => Municipio::where('id_estado', $this->chiapas())->value('id'),
        ]))->assertSessionHasNoErrors();

        $detalle = Compra::firstOrFail()->detalle->first();

        $this->assertNotNull($detalle->id_pais);
        $this->assertNull($detalle->id_estado);
        $this->assertNull($detalle->id_municipio);
    }

    public function test_la_compra_precarga_la_procedencia_del_perfil(): void
    {
        $municipio = Municipio::where('id_estado', $this->chiapas())->firstOrFail();

        $this->actingAs($this->cliente([
            'id_pais' => $this->mexico(), 'id_estado' => $this->chiapas(), 'id_municipio' => $municipio->id,
        ]), 'cliente');

        $this->get('/comprar')
            ->assertOk()
            ->assertViewHas('procedenciaCliente', fn (array $p) => $p['id_municipio'] === $municipio->id);
    }

    // ═══ Procedencia en el registro ═══

    public function test_el_registro_exige_la_procedencia_que_aplica(): void
    {
        $otroEstado = Estado::where('nombre', 'Oaxaca')->value('id');

        $this->post('/registro', $this->registro([]))->assertSessionHasErrors('id_pais');
        $this->post('/registro', $this->registro(['id_pais' => $this->mexico()]))->assertSessionHasErrors('id_estado');
        $this->post('/registro', $this->registro(['id_pais' => $this->mexico(), 'id_estado' => $this->chiapas()]))
            ->assertSessionHasErrors('id_municipio');

        $this->assertSame(0, Cliente::count());

        // Un estado sin municipios en el catálogo no pide municipio.
        $this->post('/registro', $this->registro(['id_pais' => $this->mexico(), 'id_estado' => $otroEstado]))
            ->assertSessionHasNoErrors();

        $cliente = Cliente::where('correo', 'nueva@example.com')->firstOrFail();
        $this->assertSame($otroEstado, $cliente->id_estado);
        $this->assertNull($cliente->id_municipio);
    }

    public function test_un_municipio_de_otro_estado_se_rechaza(): void
    {
        $ajeno = Municipio::create([
            'id_estado' => Estado::where('nombre', 'Oaxaca')->value('id'),
            'nombre'    => 'Juchitán',
        ]);

        $this->post('/registro', $this->registro([
            'id_pais' => $this->mexico(), 'id_estado' => $this->chiapas(), 'id_municipio' => $ajeno->id,
        ]))->assertSessionHasErrors('id_municipio');
    }
}
