<?php

namespace App\Services\Auditoria;

use App\Mail\ErrorDelSistema;
use App\Models\Cuenta;
use App\Models\ErrorSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Deja constancia de los fallos del sistema y avisa al Super Admin.
 *
 * ═══════════════════════════════════════════════════════════════════════
 *  ESTE SERVICIO NO PUEDE LANZAR EXCEPCIONES. NUNCA.
 *
 *  Corre dentro del manejador de errores de Laravel. Si revienta aquí, el
 *  visitante se queda sin pantalla de error y sin respuesta: un fallo al
 *  reportar un fallo tumba el sitio entero. Por eso todo va envuelto en
 *  try/catch y lo peor que puede pasar es que no se registre nada.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * Va en tabla aparte de `movimientos` a propósito: esa es la bitácora de
 * auditoría, con cuenta responsable y valor de rendición de cuentas. Un fallo
 * no es la operación de nadie.
 */
class RegistroErroresService
{
    /** Un mismo fallo no vuelve a avisar por correo antes de esto. */
    private const MINUTOS_ENTRE_AVISOS = 30;

    /** La traza completa puede ser enorme; con esto alcanza para ubicar. */
    private const MAX_TRAZA = 8000;

    public function registrar(Throwable $e, ?Request $request = null): ?ErrorSistema
    {
        try {
            if (! $this->debeRegistrarse($e)) {
                return null;
            }

            $registro = $this->guardar($e, $request);

            $this->avisarSiProcede($registro);

            return $registro;
        } catch (Throwable $fallo) {
            // Último recurso: al log de archivo, que no depende de la base.
            // Si la base es justamente lo que está caído, este es el único
            // rastro que va a quedar.
            Log::error('No se pudo registrar un error del sistema', [
                'original' => $e->getMessage(),
                'al_registrar' => $fallo->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Solo fallos de verdad.
     *
     * Los 404 y 405 son casi siempre bots buscando /wp-admin o /.env, y
     * registrarlos ahogaría lo que sí importa. Si algún día se quieren, se
     * cambia el mínimo en config.
     */
    private function debeRegistrarse(Throwable $e): bool
    {
        if (! config('taquilla.errores.registrar', true)) {
            return false;
        }

        $codigo = $this->codigoHttp($e);

        // Una excepción sin estado HTTP es un fallo de código: siempre entra.
        if ($codigo === null) {
            return true;
        }

        return $codigo >= config('taquilla.errores.codigo_minimo', 500);
    }

    private function guardar(Throwable $e, ?Request $request): ErrorSistema
    {
        $huella = ErrorSistema::huellaDe($e);
        $ahora  = now();

        // El mismo fallo repetido incrementa el contador en vez de insertar
        // otra fila. Sin esto, una excepción en bucle llena la base en
        // minutos y la pantalla queda inservible.
        $afectadas = DB::table('errores')
            ->where('huella', $huella)
            ->update([
                'ocurrencias' => DB::raw('ocurrencias + 1'),
                'ultima_vez'  => $ahora,
            ]);

        if ($afectadas > 0) {
            return ErrorSistema::where('huella', $huella)->first();
        }

        return ErrorSistema::create([
            'huella'      => $huella,
            'clase'       => Str::limit($e::class, 185, ''),
            'mensaje'     => $e->getMessage(),
            'archivo'     => Str::limit($e->getFile(), 250, ''),
            'linea'       => $e->getLine(),
            'codigo'      => $this->codigoHttp($e),
            'metodo'      => $request?->method(),
            'url'         => $this->urlDepurada($request),
            'id_cuenta'   => Auth::guard('web')->id(),
            'id_cliente'  => Auth::guard('cliente')->id(),
            'ip'          => $request?->ip(),
            'navegador'   => Str::limit((string) $request?->userAgent(), 400, ''),
            'traza'       => Str::limit($e->getTraceAsString(), self::MAX_TRAZA),
            'ocurrencias' => 1,
            'primera_vez' => $ahora,
            'ultima_vez'  => $ahora,
        ]);
    }

    /**
     * La URL sin la cadena de consulta.
     *
     * Ahí viajan tokens de recuperación, códigos de verificación y demás, y
     * esta tabla la lee personal del panel. Lo mismo que hace
     * BitacoraService con los campos ocultos.
     */
    private function urlDepurada(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        return Str::limit($request->url(), 500, '');
    }

    /**
     * Avisa al Super Admin, con freno.
     *
     * Solo la primera vez que aparece un fallo, y después cuando vuelva a
     * aparecer pasados MINUTOS_ENTRE_AVISOS. Sin el freno, una excepción en
     * bucle manda cientos de correos y el buzón queda inservible justo
     * cuando más se necesita leerlo.
     */
    private function avisarSiProcede(ErrorSistema $registro): void
    {
        if (! config('taquilla.errores.avisar_por_correo', true)) {
            return;
        }

        $llave = 'aviso-error:' . $registro->huella;

        if (Cache::has($llave)) {
            return;
        }

        $destino = $this->correoDelSuperAdmin();

        if ($destino === null) {
            return;
        }

        Cache::put($llave, true, now()->addMinutes(self::MINUTOS_ENTRE_AVISOS));

        // ENCOLADO, no síncrono: hablar con el SMTP puede tardar segundos y
        // esto corre mientras el visitante espera su pantalla de error.
        //
        // El precio es que si la cola es justo lo que está caído, el correo
        // no sale. Por eso la fuente de verdad es la tabla y la pantalla del
        // panel; el correo es comodidad, no el único canal.
        Mail::to($destino)->queue(new ErrorDelSistema($registro));
    }

    /**
     * Correo del Super Admin, o null si no sirve.
     *
     * OJO: AuthSeeder lo siembra como `admin@example.com`, que es relleno. Si
     * nadie lo cambió en el panel, no tiene caso intentar el envío — se
     * perdería en silencio o rebotaría.
     */
    private function correoDelSuperAdmin(): ?string
    {
        $correo = Cuenta::superAdmin()?->usuario?->email;

        if (blank($correo) || ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (str_ends_with($correo, '@example.com')) {
            Log::warning(
                'El Super Admin sigue con el correo de relleno; no se envió el aviso de error. '
                . 'Cámbialo en /admin/users.'
            );

            return null;
        }

        return $correo;
    }

    private function codigoHttp(Throwable $e): ?int
    {
        return $e instanceof HttpExceptionInterface
            ? $e->getStatusCode()
            : null;
    }
}
