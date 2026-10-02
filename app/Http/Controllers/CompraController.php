<?php

namespace App\Http\Controllers;

use App\Exceptions\DiaNoDisponibleException;
use App\Models\AforoDiario;
use App\Models\Compra;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Pago;
use App\Models\Pais;
use App\Models\Rubro;
use App\Services\Acceso\QrImagenService;
use App\Services\Cliente\SesionInvitado;
use App\Services\Pago\PasarelaPago;
use App\Services\Venta\CotizarCompraService;
use App\Services\Venta\RegistrarCompraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Portal de compra del visitante (brief §7, guard `cliente`).
 *
 * Compra quien tiene sesión de cliente o quien verificó su correo como
 * invitado (middleware `comprador`). Las pantallas posteriores a la compra
 * existen dos veces en las rutas —con sesión y con enlace firmado— y aquí
 * comparten método: lo único que cambia es cómo se autoriza el folio.
 *
 * El controlador solo orquesta: valida, llama al servicio y responde. Cero
 * reglas de negocio aquí (§6).
 */
class CompraController extends Controller
{
    public function __construct(
        private readonly CotizarCompraService $cotizador,
        private readonly RegistrarCompraService $registrador,
        private readonly PasarelaPago $pasarela,
        private readonly SesionInvitado $invitado,
    ) {
    }

    public function crear(Request $request)
    {
        $fecha = $request->query('fecha');

        $abiertos = AforoDiario::abiertosDesdeHoy()
            ->orderBy('fecha')
            ->get()
            ->map(fn ($dia) => $dia->fecha->toDateString())
            ->flip();

        return view('publico.comprar', [
            'rubros'         => $fecha
                ? Rubro::vigentes($fecha)->with('tipoAcceso')->orderBy('tipo')->get()
                : Rubro::vigentes()->with('tipoAcceso')->orderBy('tipo')->get(),
            'fechaElegida'    => $fecha,
            // Solo para quien compra sin cuenta: a dónde llegarán los boletos.
            'correoInvitado'  => Auth::guard('cliente')->check() ? null : $this->invitado->correo(),
            'meses'           => $this->rejillaDeMeses($abiertos),
            'hayDiasAbiertos' => $abiertos->isNotEmpty(),
            'paises'   => Pais::activos()->orderBy('nombre')->get(),
            'estados'  => Estado::activos()->orderBy('nombre')->get(),
            'municipios' => Municipio::activos()->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Devuelve el total calculado EN EL SERVIDOR. La pantalla lo muestra,
     * pero nunca lo manda de regreso (brief §4.2).
     */
    public function cotizar(Request $request)
    {
        $datos = $this->validarCarrito($request);

        $cotizacion = $this->cotizador->cotizar($datos['renglones'], $datos['fecha_visita']);

        return response()->json([
            'total_centavos'   => $cotizacion->totalCentavos,
            'total_formateado' => $cotizacion->totalFormateado(),
            'pases'            => $cotizacion->pasesTotal,
        ]);
    }

    public function guardar(Request $request)
    {
        $datos = $this->validarCarrito($request);

        // Se vuelve a cotizar aquí: lo que el navegador vio es irrelevante.
        $cotizacion = $this->cotizador->cotizar($datos['renglones'], $datos['fecha_visita']);

        // Con sesión manda la cuenta; sin ella, el correo que el invitado
        // verificó (el middleware `comprador` garantiza que hay uno de los dos).
        $cliente = Auth::guard('cliente')->user();

        try {
            $compra = $cliente
                ? $this->registrador->registrar($cliente, $cotizacion, $datos['fecha_visita'])
                : $this->registrador->registrarInvitado($this->invitado->correo(), $cotizacion, $datos['fecha_visita']);
        } catch (DiaNoDisponibleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Alta del cobro en la pasarela y registro del pago iniciado.
        $cobro = $this->pasarela->crearCobro($compra);

        Pago::create([
            'id_compra'          => $compra->id,
            'proveedor'          => config('taquilla.pago.pasarela'),
            'referencia_externa' => $cobro->referenciaExterna,
            'monto_centavos'     => $cobro->montoCentavos,
            'estado'             => Pago::INICIADO,
        ]);

        return redirect()->away($cobro->urlRedireccion);
    }

    /**
     * Pantalla de "procesando" tras volver de la pasarela.
     *
     * NO emite el QR ni marca nada como pagado (brief §4.4): solo refleja el
     * estado que el webhook ya haya escrito. Si el webhook aún no llega, la
     * pantalla lo dice y el visitante puede recargar.
     */
    public function retorno(string $folio)
    {
        $compra = $this->compraAutorizada($folio);

        return view('publico.retorno', ['compra' => $compra]);
    }

    public function index()
    {
        $compras = Compra::where('id_cliente', Auth::guard('cliente')->id())
            ->orderByDesc('fecha_compra')
            ->paginate(20);

        return view('publico.mis-compras', ['compras' => $compras]);
    }

    public function ver(string $folio)
    {
        $compra = $this->compraAutorizada($folio);
        $compra->load('detalle');

        return view('publico.compra', ['compra' => $compra]);
    }

    /**
     * Imagen del QR, generada al vuelo. Solo el dueño de la compra puede
     * verla, y solo si está pagada.
     */
    public function qr(string $folio, QrImagenService $imagenes)
    {
        $compra = $this->compraAutorizada($folio);

        abort_unless($compra->estaPagada() && filled($compra->qr_token), 404);

        return response($imagenes->svg($compra), 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // === Apoyo ===

    /**
     * La compra que esta petición tiene derecho a ver. Un folio ajeno
     * responde 404, no 403, para no confirmar que ese folio existe.
     *
     * - Rutas `invitado.*`: la firma del enlace ya se validó (middleware
     *   `signed`) y solo abre compras hechas como invitado.
     * - Rutas con sesión: solo las del cliente autenticado.
     *
     * Nunca se busca por `id_cliente` nulo: eso abriría las compras de todos
     * los invitados a cualquiera que adivinara un folio consecutivo.
     */
    private function compraAutorizada(string $folio): Compra
    {
        $compra = Compra::where('folio', $folio);

        return request()->routeIs('invitado.*')
            ? $compra->whereNotNull('correo_invitado')->firstOrFail()
            : $compra->where('id_cliente', Auth::guard('cliente')->user()->id)->firstOrFail();
    }

    /**
     * Del carrito solo se aceptan rubro, cantidades y procedencia.
     * Cualquier precio, importe o total que venga en el request se ignora:
     * ni siquiera se valida, porque no se lee (brief §4.2).
     */
    private function validarCarrito(Request $request): array
    {
        $this->normalizarCantidades($request);

        $tope = (int) config('taquilla.compra.max_por_campo');

        return $request->validate([
            'fecha_visita'                => ['required', 'date', 'after_or_equal:today'],
            'renglones'                   => ['required', 'array', 'min:1'],
            'renglones.*.id_rubro'        => ['required', 'exists:rubros,id'],
            'renglones.*.cant_hombre'     => ['nullable', 'integer', 'min:0', "max:{$tope}"],
            'renglones.*.cant_mujer'      => ['nullable', 'integer', 'min:0', "max:{$tope}"],
            'renglones.*.id_pais'         => ['nullable', 'exists:paises,id'],
            'renglones.*.id_estado'       => ['nullable', 'exists:estados,id'],
            'renglones.*.id_municipio'    => ['nullable', 'exists:municipios,id'],
        ], [
            'fecha_visita.required' => 'Elige en el calendario el día de tu visita.',
        ], [
            'fecha_visita'            => 'fecha de visita',
            'renglones'               => 'boletos',
            'renglones.*.cant_hombre' => 'cantidad de hombres',
            'renglones.*.cant_mujer'  => 'cantidad de mujeres',
        ]);
    }

    /**
     * «03» es un 3. Un campo numérico del navegador conserva el cero inicial
     * cuando se teclea junto al 0 que ya estaba, y la regla `integer` de
     * Laravel lo rechaza: la persona veía «debe ser un número entero» después
     * de haber escrito un número entero. Solo se tocan cadenas de puros
     * dígitos; cualquier otra cosa llega intacta a la validación.
     */
    private function normalizarCantidades(Request $request): void
    {
        $renglones = $request->input('renglones');

        if (! is_array($renglones)) {
            return;
        }

        foreach ($renglones as $i => $renglon) {
            foreach (['cant_hombre', 'cant_mujer'] as $campo) {
                $valor = is_array($renglon) ? ($renglon[$campo] ?? null) : null;

                if (is_string($valor) && ctype_digit($valor)) {
                    $renglones[$i][$campo] = (int) $valor;
                }
            }
        }

        $request->merge(['renglones' => $renglones]);
    }

    /**
     * Rejilla del calendario de compra: los próximos meses, con cada día
     * marcado como abierto o no.
     *
     * Esto es armado de vista, no regla de negocio: la única fuente sobre qué
     * días abre el zoológico sigue siendo el calendario de operación. Se
     * pintan los meses completos —con los huecos del inicio— porque un
     * calendario que empieza a media semana se lee mal.
     */
    private function rejillaDeMeses(\Illuminate\Support\Collection $abiertos): array
    {
        $meses   = max(1, (int) config('taquilla.calendario.meses_a_la_venta'));
        $hoy     = Carbon::today();
        $rejilla = [];

        for ($m = 0; $m < $meses; $m++) {
            $primero = $hoy->copy()->startOfMonth()->addMonths($m);
            $ultimo  = $primero->copy()->endOfMonth();

            // La semana arranca en lunes; los días previos al día 1 van vacíos.
            $celdas = array_fill(0, $primero->dayOfWeekIso - 1, null);

            for ($dia = $primero->copy(); $dia->lte($ultimo); $dia->addDay()) {
                $fecha = $dia->toDateString();

                $celdas[] = [
                    'fecha'   => $fecha,
                    'numero'  => $dia->day,
                    'abierto' => $abiertos->has($fecha),
                ];
            }

            $rejilla[] = [
                'clave'  => $primero->format('Y-m'),
                'titulo' => ucfirst($primero->translatedFormat('F Y')),
                'celdas' => $celdas,
            ];
        }

        return $rejilla;
    }
}
