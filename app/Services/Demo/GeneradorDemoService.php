<?php

namespace App\Services\Demo;

use App\Jobs\ExpirarComprasPendientes;
use App\Models\Acceso;
use App\Models\AforoDiario;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cuenta;
use App\Models\ErrorSistema;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Pago;
use App\Models\Pais;
use App\Models\Rubro;
use App\Models\Usuario;
use App\Services\Acceso\ValidarAccesoService;
use App\Services\Auditoria\BitacoraService;
use App\Services\Operacion\GenerarCalendarioService;
use App\Services\Pago\ConfirmarPagoService;
use App\Services\Pago\NotificacionPago;
use App\Services\Pago\PasarelaSimulada;
use App\Services\Venta\CancelarCompraService;
use App\Services\Venta\CotizarCompraService;
use App\Services\Venta\ReembolsarCompraService;
use App\Services\Venta\RegistrarCompraService;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Llena el sistema con una historia verosímil para ver tableros y reportes.
 *
 * No inserta filas a mano: recorre el calendario día por día con el reloj
 * adelantado (Carbon::setTestNow) y llama a los MISMOS servicios que usa el
 * portal —cotizar, registrar, confirmar pago, validar acceso, cancelar,
 * reembolsar, expirar—. Así los folios salen consecutivos, las fechas
 * cuadran entre tablas y la bitácora se llena sola. Si un servicio cambia,
 * los datos de demostración cambian con él.
 *
 * Solo vende las tarifas oficiales (DemoSeeder::TARIFAS_OFICIALES).
 *
 * Todo lo que crea es reconocible para poder quitarlo: los correos terminan
 * en DOMINIO, y el operador de la puerta es la cuenta OPERADOR.
 *
 * ponytail: los porcentajes de abajo son supuestos razonables, no datos
 * reales del zoológico. Upgrade path: cuando haya operación real, sustituir
 * las constantes por las proporciones que salgan de los cortes.
 */
class GeneradorDemoService
{
    public const DOMINIO  = '@demo.invalid';
    public const OPERADOR = 'demo_taquilla';

    private const TORNIQUETE     = 'demo';
    private const CLIENTES       = 80;
    private const COMPRAS_AL_DIA = 12;      // día entre semana; el fin de semana lleva más
    private const FACTOR_FIN_DE_SEMANA = 2.4;
    private const DIAS_MAX_DE_ANTICIPACION = 14;

    // Qué le pasa a cada compra, en porcentaje.
    private const PCT_INVITADO        = 30;
    private const PCT_ABANDONA        = 6;   // nunca paga → expira
    private const PCT_PAGO_RECHAZADO  = 4;
    private const PCT_CANCELADA       = 3;   // de las pagadas
    private const PCT_REEMBOLSADA     = 60;  // de las canceladas
    private const PCT_NO_SE_PRESENTA  = 5;   // de las que llegan vigentes al día
    private const PCT_ENTRA_INCOMPLETO = 8;
    private const PCT_ESCANEO_RECHAZADO = 4; // intentos fallidos por cada visita

    // Qué tan seguido aparece cada tarifa en una compra. Casi todo grupo
    // lleva un adulto; una tarifa oficial que no esté aquí usa PCT_LLEVA_OTRA.
    private const PCT_LLEVA = ['Adulto' => 92, 'Niños' => 55, 'Tercera Edad' => 15];
    private const PCT_LLEVA_OTRA = 30;

    // De los visitantes de Chiapas, cuántos son de la capital.
    private const PCT_DE_LA_CAPITAL = 45;
    private const CAPITAL = 'Tuxtla Gutiérrez';

    private const NOMBRES   = ['María', 'José', 'Guadalupe', 'Juan', 'Fernanda', 'Luis', 'Sofía', 'Carlos', 'Daniela', 'Miguel', 'Valeria', 'Jorge', 'Ximena', 'Roberto', 'Camila', 'Andrés'];
    private const APELLIDOS = ['Hernández', 'López', 'Gómez', 'Pérez', 'Ruiz', 'Cruz', 'Morales', 'Vázquez', 'Jiménez', 'Domínguez', 'Aguilar', 'Méndez', 'Santiago', 'Velasco', 'Zenteno', 'Coutiño'];

    private Randomizer $azar;
    private Collection $rubros;
    private Collection $clientes;
    private Cuenta $operador;
    private array $procedencias;

    /** Compras pagadas que esperan su día de visita: [fecha => Compra[]]. */
    private array $visitas = [];

    private array $resumen = [];

    /** El momento real en que corre: nada de lo generado puede quedar fechado después. */
    private Carbon $ahora;

    public function __construct(
        private readonly CotizarCompraService $cotizador,
        private readonly RegistrarCompraService $registrador,
        private readonly ConfirmarPagoService $pagos,
        private readonly PasarelaSimulada $pasarela,
        private readonly ValidarAccesoService $accesos,
        private readonly CancelarCompraService $cancelador,
        private readonly ReembolsarCompraService $reembolsos,
        private readonly GenerarCalendarioService $calendario,
        private readonly BitacoraService $bitacora,
    ) {
    }

    /** ¿Ya hay datos de demostración en la base? */
    public function yaHayDatos(): bool
    {
        return $this->comprasDemo()->exists();
    }

    /**
     * @return array<string, int> conteos por desenlace, para el resumen del comando
     */
    public function generar(int $meses, int $semilla): array
    {
        $ahora         = $this->ahora = Carbon::now();
        $relojAnterior = Carbon::getTestNow();

        $this->azar    = new Randomizer(new Mt19937($semilla));
        $this->visitas = [];
        $this->resumen = [];

        // Ni un correo sale de aquí: los comprobantes van a direcciones que no existen.
        Mail::fake();

        try {
            $inicio = $ahora->copy()->subMonthsNoOverflow($meses)->startOfDay();

            $this->preparar($inicio, $ahora);

            for ($dia = $inicio->copy(); $dia->lte($ahora); $dia->addDay()) {
                $this->venderDurante($dia, $ahora);
                $this->recibirVisitas($dia, $ahora);

                // Lo que nadie pagó expira, como lo haría el scheduler.
                $this->reloj($dia->copy()->endOfDay());
                (new ExpirarComprasPendientes())->handle($this->bitacora);
            }

            // Lo comprado con anticipación para los próximos días.
            foreach ($this->visitas as $pendientes) {
                $this->resumen['pagadas por visitar'] = ($this->resumen['pagadas por visitar'] ?? 0) + count($pendientes);
            }

            Carbon::setTestNow($ahora);
            $this->sembrarFallos();
        } finally {
            Carbon::setTestNow($relojAnterior);
        }

        return $this->resumen;
    }

    /**
     * Quita todo lo que generar() creó. Los folios consumidos no se
     * recuperan, salvo que no quede ninguna compra: entonces el contador
     * vuelve a cero.
     *
     * @return array<string, int>
     */
    public function limpiar(): array
    {
        return DB::transaction(function () {
            $idsCompras = $this->comprasDemo()->pluck('id');
            $operador   = Cuenta::withTrashed()->where('username', self::OPERADOR)->first();

            $borrado = [
                'accesos'  => Acceso::whereIn('id_compra', $idsCompras)
                    ->when($operador, fn ($q) => $q->orWhere('id_cuenta', $operador->id_cuenta))
                    ->delete(),
                'pagos'    => Pago::whereIn('id_compra', $idsCompras)->delete(),
                'bitacora' => DB::table('movimientos')->where('tabla', 'compras')
                    ->whereIn('registro_id', $idsCompras->map(fn ($id) => (string) $id))->delete(),
                // El detalle cae en cascada. forceDelete: son datos de mentira, no hay nada que auditar.
                'compras'  => $this->comprasDemo()->forceDelete(),
                'clientes' => Cliente::withTrashed()->where('correo', 'like', '%' . self::DOMINIO)->forceDelete(),
                'fallos'   => ErrorSistema::where('huella', 'like', 'demo-%')->delete(),
            ];

            if ($operador) {
                $usuario = $operador->id_usuario;
                $operador->forceDelete();
                Usuario::withTrashed()->where('id_usuario', $usuario)->forceDelete();
            }

            if (! Compra::withTrashed()->exists()) {
                DB::table('folios')->update(['ultimo' => 0]);
            }

            return $borrado;
        });
    }

    // ═══ Preparación ═══

    private function preparar(Carbon $inicio, Carbon $ahora): void
    {
        // Tarifas oficiales, y vigentes desde antes del primer día simulado:
        // sin eso no se podría cotizar una visita del pasado.
        (new DemoSeeder())->tarifas();

        Rubro::whereIn('tipo', array_keys(DemoSeeder::TARIFAS_OFICIALES))
            ->whereDate('vigente_desde', '>', $inicio->toDateString())
            ->update(['vigente_desde' => $inicio->toDateString()]);

        $this->rubros = Rubro::whereIn('tipo', array_keys(DemoSeeder::TARIFAS_OFICIALES))->get()->values();

        // El calendario del periodo: el pasado para tener historia, y lo
        // próximo para las compras con anticipación.
        $this->calendario->generar($inicio, $ahora->copy()->addDays(self::DIAS_MAX_DE_ANTICIPACION));

        $this->operador = $this->operador();
        $this->clientes = $this->clientes($inicio);
        $this->procedencias = $this->procedencias();
    }

    /** Quien escanea en la puerta. Inactiva y con clave aleatoria: nadie entra con ella. */
    private function operador(): Cuenta
    {
        $cuenta = Cuenta::where('username', self::OPERADOR)->first();

        if ($cuenta) {
            return $cuenta;
        }

        $usuario = Usuario::create(['nombre' => 'Taquilla (demostración)', 'puesto' => 'Taquilla']);

        return Cuenta::create([
            'username'   => self::OPERADOR,
            'password'   => Hash::make(Str::random(40)),
            'estado'     => 'inactivo',
            'id_usuario' => $usuario->id_usuario,
            'id_rol'     => DB::table('roles')->where('nombre', 'Taquilla')->value('id_rol'),
        ]);
    }

    private function clientes(Carbon $inicio): Collection
    {
        Carbon::setTestNow($inicio);

        return Collection::times(self::CLIENTES, function (int $n) {
            return Cliente::firstOrCreate(
                ['correo' => "visitante{$n}" . self::DOMINIO],
                [
                    // Sin contraseña: son cuentas de relleno, nadie inicia sesión con ellas.
                    'password'             => null,
                    'nombre'               => $this->uno(self::NOMBRES),
                    'apellidos'            => $this->uno(self::APELLIDOS) . ' ' . $this->uno(self::APELLIDOS),
                    'fecha_nacimiento'     => Carbon::create($this->azar->getInt(1960, 2004), $this->azar->getInt(1, 12), $this->azar->getInt(1, 28)),
                    'genero'               => $this->uno(['Mujer', 'Hombre']),
                    'telefono'             => '961' . $this->azar->getInt(1000000, 9999999),
                    'correo_verificado_en' => Carbon::now(),
                ],
            );
        });
    }

    /**
     * De dónde viene la gente, ya repartido: la mayoría de Chiapas, luego del
     * resto del país, unos pocos del extranjero y algunos que no lo dicen.
     */
    private function procedencias(): array
    {
        $mexico  = Pais::where('nombre', 'México')->value('id');
        $chiapas = Estado::where('nombre', 'Chiapas')->value('id');

        $municipios = Municipio::where('id_estado', $chiapas)->pluck('id')->all();
        $capital    = Municipio::where('id_estado', $chiapas)->where('nombre', self::CAPITAL)->value('id');
        $estados    = Estado::where('id', '!=', $chiapas)->pluck('id')->all();
        $paises     = Pais::where('id', '!=', $mexico)->pluck('id')->all();

        return [
            60 => fn () => ['id_pais' => $mexico, 'id_estado' => $chiapas, 'id_municipio' => $capital && $this->porcentaje(self::PCT_DE_LA_CAPITAL)
                ? $capital
                : $this->uno($municipios)],
            82 => fn () => ['id_pais' => $mexico, 'id_estado' => $this->uno($estados)],
            94 => fn () => ['id_pais' => $this->uno($paises)],
            100 => fn () => [],
        ];
    }

    // ═══ Un día de ventas ═══

    private function venderDurante(Carbon $dia, Carbon $ahora): void
    {
        $cuantas = (int) round(
            self::COMPRAS_AL_DIA
            * ($dia->isWeekend() ? self::FACTOR_FIN_DE_SEMANA : 1)
            * $this->azar->getInt(60, 140) / 100
        );

        // Las horas se sortean y se ordenan: los folios salen en orden cronológico.
        $momentos = Collection::times($cuantas, fn () => $dia->copy()->setTime($this->azar->getInt(7, 22), $this->azar->getInt(0, 59)))
            ->filter(fn (Carbon $momento) => $momento->lte($ahora))
            ->sort();

        foreach ($momentos as $momento) {
            $this->reloj($momento);
            $this->comprar($momento);
        }
    }

    private function comprar(Carbon $momento): void
    {
        $fechaVisita = $this->diaDeVisita($momento);

        if ($fechaVisita === null) {
            return;
        }

        $cotizacion = $this->cotizador->cotizar($this->carrito(), $fechaVisita);

        $compra = $this->porcentaje(self::PCT_INVITADO)
            ? $this->registrador->registrarInvitado('invitado' . $this->azar->getInt(1, 400) . self::DOMINIO, $cotizacion, $fechaVisita)
            : $this->registrador->registrar($this->uno($this->clientes->all()), $cotizacion, $fechaVisita);

        if ($this->porcentaje(self::PCT_ABANDONA)) {
            $this->contar('abandonadas (expiran)');

            return;
        }

        // El mismo camino del portal: se da de alta el cobro y llega el webhook.
        $cobro = $this->pasarela->crearCobro($compra);

        Pago::create([
            'id_compra'          => $compra->id,
            'proveedor'          => PasarelaSimulada::PROVEEDOR,
            'referencia_externa' => $cobro->referenciaExterna,
            'monto_centavos'     => $cobro->montoCentavos,
            'estado'             => Pago::INICIADO,
        ]);

        $aprobado = ! $this->porcentaje(self::PCT_PAGO_RECHAZADO);

        $this->reloj($momento->copy()->addMinutes($this->azar->getInt(1, 6)));

        $this->pagos->confirmar(new NotificacionPago(
            firmaValida:       true,
            referenciaExterna: $cobro->referenciaExterna,
            estado:            $aprobado ? NotificacionPago::APROBADO : NotificacionPago::RECHAZADO,
            montoCentavos:     $cobro->montoCentavos,
            autorizacion:      $aprobado ? 'DEMO-' . $this->azar->getInt(100000, 999999) : null,
            payload:           ['origen' => 'demostracion'],
        ), PasarelaSimulada::PROVEEDOR);

        if (! $aprobado) {
            $this->contar('pago rechazado (expiran)');

            return;
        }

        $this->contar('pagos aprobados');

        if ($this->porcentaje(self::PCT_CANCELADA)) {
            $this->cancelar($compra);

            return;
        }

        $this->visitas[$fechaVisita][] = $compra;
    }

    private function cancelar(Compra $compra): void
    {
        $this->reloj(Carbon::now()->addHours($this->azar->getInt(1, 5)));

        $cancelada = $this->cancelador->cancelar($compra, $this->uno([
            'El visitante avisó que no podrá asistir',
            'Cobro duplicado reportado por el visitante',
            'Cierre del zoológico por contingencia',
        ]));

        if ($this->porcentaje(self::PCT_REEMBOLSADA)) {
            $this->reloj(Carbon::now()->addDays($this->azar->getInt(1, 4)));
            $this->reembolsos->reembolsar($cancelada, 'Devuelto en el portal del banco (demostración)');
            $this->contar('reembolsadas');

            return;
        }

        $this->contar('canceladas sin reembolso');
    }

    /** Renglones de las tarifas oficiales, cada uno con su procedencia. */
    private function carrito(): array
    {
        $rubros = $this->rubros->filter(
            fn (Rubro $rubro) => $this->porcentaje(self::PCT_LLEVA[$rubro->tipo] ?? self::PCT_LLEVA_OTRA)
        );

        // Nadie compra cero boletos.
        if ($rubros->isEmpty()) {
            $rubros = $this->rubros->take(1);
        }

        return $rubros
            ->map(function (Rubro $rubro) {
                $personas = $this->azar->getInt(1, 4);
                $hombres  = $this->azar->getInt(0, $personas);

                return [
                    'id_rubro'    => $rubro->id,
                    'cant_hombre' => $hombres,
                    'cant_mujer'  => $personas - $hombres,
                    ...$this->procedencia(),
                ];
            })
            ->values()
            ->all();
    }

    private function procedencia(): array
    {
        $tiro = $this->azar->getInt(1, 100);

        foreach ($this->procedencias as $hasta => $armar) {
            if ($tiro <= $hasta) {
                return $armar();
            }
        }

        return [];
    }

    /**
     * La mayoría compra para hoy o los próximos días. Devuelve el primer día
     * abierto a partir de la fecha sorteada, o null si el calendario se acaba.
     */
    private function diaDeVisita(Carbon $momento): ?string
    {
        $anticipacion = $this->porcentaje(65)
            ? $this->azar->getInt(0, 3)
            : $this->azar->getInt(4, self::DIAS_MAX_DE_ANTICIPACION);

        // Después del cierre ya no se compra para el mismo día.
        if ($anticipacion === 0 && $momento->format('H:i') >= config('taquilla.horario.cierre')) {
            $anticipacion = 1;
        }

        return AforoDiario::where('fecha', '>=', $momento->copy()->addDays($anticipacion)->toDateString())
            ->where('cerrado', false)
            ->orderBy('fecha')
            ->value('fecha')
            ?->toDateString();
    }

    // ═══ Un día en la puerta ═══

    private function recibirVisitas(Carbon $dia, Carbon $ahora): void
    {
        $fecha  = $dia->toDateString();
        $abre   = $dia->copy()->setTimeFromTimeString(config('taquilla.horario.apertura'));
        $cierra = $dia->copy()->setTimeFromTimeString(config('taquilla.horario.cierre'));

        foreach ($this->visitas[$fecha] ?? [] as $compra) {
            // Nadie llega antes de haber comprado.
            $llegada = max(
                $abre->copy()->addMinutes($this->azar->getInt(0, (int) $abre->diffInMinutes($cierra) - 30)),
                $compra->fecha_compra->copy()->addMinutes(10),
            );

            // Quien compró para hoy y todavía no llega conserva su boleto vigente.
            if ($llegada->gt($ahora)) {
                $this->contar('pagadas por visitar');

                continue;
            }

            if ($llegada->gt($cierra) || $this->porcentaje(self::PCT_NO_SE_PRESENTA)) {
                $this->contar('no se presentaron');

                continue;
            }

            $this->reloj($llegada);

            if ($this->porcentaje(self::PCT_ESCANEO_RECHAZADO)) {
                $this->escaneoRechazado($compra, $fecha);
            }

            $entran = $this->porcentaje(self::PCT_ENTRA_INCOMPLETO) && $compra->pases_total > 1
                ? $this->azar->getInt(1, $compra->pases_total - 1)
                : $compra->pases_total;

            // Casi todos muestran el QR; alguno dicta el folio.
            $codigo = $this->porcentaje(90) ? $compra->fresh()->qr_token : $compra->folio;

            $this->accesos->validar($codigo, $entran, self::TORNIQUETE, $this->operador, $fecha);

            $this->contar($entran === $compra->pases_total ? 'utilizadas' : 'acceso parcial');
        }

        unset($this->visitas[$fecha]);
    }

    /** Lo que también pasa en la puerta: un folio mal dictado o un código que no es de hoy. */
    private function escaneoRechazado(Compra $compra, string $fecha): void
    {
        $this->porcentaje(50)
            ? $this->accesos->consultar('ZM-0000-' . $this->azar->getInt(100000, 999999), self::TORNIQUETE, $this->operador, $fecha)
            : $this->accesos->consultar($compra->folio, self::TORNIQUETE, $this->operador, Carbon::parse($fecha)->subDay()->toDateString());

        $this->contar('escaneos rechazados');
    }

    // ═══ Fallos del sistema ═══

    /** Unos pocos, para que la pantalla de fallos y su aviso no salgan vacíos. */
    private function sembrarFallos(): void
    {
        $fallos = [
            ['Illuminate\Database\QueryException', 'SQLSTATE[08006] No se pudo conectar con la base de datos', 3, false],
            ['Symfony\Component\Mailer\Exception\TransportException', 'El servidor de correo rechazó la conexión', 5, false],
            ['ErrorException', 'Undefined array key "id_municipio"', 1, true],
        ];

        foreach ($fallos as $n => [$clase, $mensaje, $veces, $atendido]) {
            $visto = Carbon::now()->subDays($n * 3 + 1);

            ErrorSistema::updateOrCreate(['huella' => "demo-{$n}"], [
                'clase'       => $clase,
                'mensaje'     => $mensaje . ' (demostración)',
                'archivo'     => 'app/Demostracion.php',
                'linea'       => 1,
                'codigo'      => 500,
                'metodo'      => 'GET',
                'url'         => url('/comprar'),
                'ocurrencias' => $veces,
                'primera_vez' => $visto->copy()->subHours(2),
                'ultima_vez'  => $visto,
                'atendido_en' => $atendido ? $visto->copy()->addHour() : null,
            ]);
        }

        $this->resumen['fallos del sistema'] = count($fallos);
    }

    // ═══ Apoyo ═══

    private function comprasDemo()
    {
        $patron = '%' . self::DOMINIO;

        return Compra::withTrashed()->where(fn ($q) => $q
            ->where('correo_invitado', 'like', $patron)
            ->orWhereHas('cliente', fn ($c) => $c->withTrashed()->where('correo', 'like', $patron)));
    }

    /** Adelanta el reloj al momento simulado, sin pasarse nunca del presente real. */
    private function reloj(Carbon $momento): void
    {
        Carbon::setTestNow(min($momento, $this->ahora));
    }

    private function porcentaje(int $probabilidad): bool
    {
        return $this->azar->getInt(1, 100) <= $probabilidad;
    }

    private function uno(array $opciones): mixed
    {
        return $opciones[$this->azar->getInt(0, count($opciones) - 1)];
    }

    private function contar(string $que): void
    {
        $this->resumen[$que] = ($this->resumen[$que] ?? 0) + 1;
    }
}
