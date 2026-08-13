<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Pais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Ingreso con Google para visitantes (brief §7).
 *
 * Regla del brief: se enlaza una cuenta local con OAuth SOLO si el correo ya
 * está verificado. Si existe una cuenta local sin verificar, no se enlaza:
 * primero hay que probar la posesión del correo con el código de 6 dígitos.
 *
 * Google entrega únicamente correo y nombre. Como `clientes` exige fecha de
 * nacimiento, género y teléfono, un alta nueva por OAuth NO crea el registro
 * de inmediato: guarda los datos en sesión y pide completar el perfil. Así no
 * se inventan valores ni se relajan las restricciones de la tabla.
 */
class GoogleAuthController extends Controller
{
    private const SESION_PENDIENTE = 'oauth_google_pendiente';

    public function __construct()
    {
        // Sin credenciales configuradas, la función no existe.
        abort_unless(filled(config('services.google.client_id')), 404);
    }

    public function redirigir()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $usuarioGoogle = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            // El `state` guardado al redirigir no coincide con el que devuelve
            // Google. Casi siempre es que la sesión se perdió por navegar en
            // un host distinto al de GOOGLE_REDIRECT_URI (127.0.0.1 contra
            // localhost son sesiones distintas para el navegador), o porque
            // la persona tardó tanto que la sesión caducó.
            report($e);

            return redirect()->route('portal.ingresar')
                ->with('error', 'La sesión expiró durante el ingreso con Google. Vuelve a intentarlo.');
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('portal.ingresar')
                ->with('error', 'No pudimos completar el ingreso con Google. Intenta de nuevo.');
        }

        $correo = mb_strtolower(trim((string) $usuarioGoogle->getEmail()));

        if ($correo === '') {
            return redirect()->route('portal.ingresar')
                ->with('error', 'Tu cuenta de Google no compartió un correo electrónico.');
        }

        // ── ¿Ya la habíamos enlazado? ──
        $porProveedor = Cliente::where('proveedor_oauth', 'google')
            ->where('proveedor_oauth_id', $usuarioGoogle->getId())
            ->first();

        if ($porProveedor) {
            return $this->iniciarSesion($request, $porProveedor);
        }

        $local = Cliente::where('correo', $correo)->first();

        if ($local) {
            // Regla del brief: enlazar solo si el correo ya está verificado.
            if (! $local->correoVerificado()) {
                return redirect()->route('portal.verificar', ['correo' => $correo])
                    ->with('error', 'Ya existe una cuenta con ese correo sin verificar. '
                        . 'Verifícala con el código antes de enlazarla con Google.');
            }

            $local->update([
                'proveedor_oauth'    => 'google',
                'proveedor_oauth_id' => $usuarioGoogle->getId(),
            ]);

            return $this->iniciarSesion($request, $local);
        }

        // ── Alta nueva: faltan datos que Google no entrega ──
        $request->session()->put(self::SESION_PENDIENTE, [
            'correo'    => $correo,
            'oauth_id'  => $usuarioGoogle->getId(),
            'nombre'    => $usuarioGoogle->getName() ?? '',
        ]);

        return redirect()->route('portal.completar');
    }

    public function mostrarCompletar(Request $request)
    {
        $pendiente = $request->session()->get(self::SESION_PENDIENTE);

        if (! $pendiente) {
            return redirect()->route('portal.registro');
        }

        return view('publico.auth.completar', [
            'pendiente' => $pendiente,
            'paises'    => Pais::activos()->orderBy('nombre')->get(),
            'estados'   => Estado::activos()->orderBy('nombre')->get(),
        ]);
    }

    public function completar(Request $request)
    {
        $pendiente = $request->session()->get(self::SESION_PENDIENTE);

        if (! $pendiente) {
            return redirect()->route('portal.registro');
        }

        $datos = $request->validate([
            'nombre'           => ['required', 'string', 'max:120'],
            'apellidos'        => ['required', 'string', 'max:160'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'genero'           => ['required', 'string', 'max:40'],
            'telefono'         => ['required', 'string', 'max:30'],
            'id_pais'          => ['nullable', 'exists:paises,id'],
            'id_estado'        => ['nullable', 'exists:estados,id'],
        ]);

        $cliente = Cliente::create([
            ...$datos,
            'correo'             => $pendiente['correo'],
            'password'           => null,          // entró por OAuth
            'proveedor_oauth'    => 'google',
            'proveedor_oauth_id' => $pendiente['oauth_id'],
            // Google ya comprobó la posesión del correo: no hace falta código.
            'correo_verificado_en' => now(),
        ]);

        $request->session()->forget(self::SESION_PENDIENTE);

        return $this->iniciarSesion($request, $cliente);
    }

    private function iniciarSesion(Request $request, Cliente $cliente)
    {
        Auth::guard('cliente')->login($cliente);
        $request->session()->regenerate();

        return redirect()->intended(route('compras.crear'));
    }
}
