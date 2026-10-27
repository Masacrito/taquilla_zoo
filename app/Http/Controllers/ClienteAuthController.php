<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pais;
use App\Models\Estado;
use App\Models\Municipio;
use App\Services\Cliente\Procedencia;
use App\Services\Cliente\VerificacionCorreoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Autenticación del visitante (guard `cliente`, brief §3.2 y §7).
 *
 * Nada de esto toca el guard `web`: un cliente jamás debe alcanzar el panel
 * interno, y una cuenta interna jamás debe poder comprar.
 */
class ClienteAuthController extends Controller
{
    public function __construct(
        private readonly VerificacionCorreoService $verificacion,
        private readonly Procedencia $procedencia,
    ) {
    }

    // ═══ Registro ═══

    public function mostrarRegistro()
    {
        return view('publico.auth.registro', [
            'paises'  => Pais::activos()->orderBy('nombre')->get(),
            'estados' => Estado::activos()->orderBy('nombre')->get(),
            'municipios' => Municipio::activos()->orderBy('nombre')->get(),
        ]);
    }

    public function registrar(Request $request)
    {
        $datos = $request->validate([
            'correo'           => ['required', 'email', 'max:160', 'unique:clientes,correo'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'nombre'           => ['required', 'string', 'max:120'],
            'apellidos'        => ['required', 'string', 'max:160'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'genero'           => ['required', 'string', 'max:40'],
            'telefono'         => ['required', 'string', 'max:30'],
            // Obligatoria desde 2026-10-05 (estadísticas). Qué tanto se pide
            // según el país y el estado lo decide Procedencia.
            'id_pais'          => ['required', 'exists:paises,id'],
            'id_estado'        => ['nullable', 'exists:estados,id'],
            'id_municipio'     => ['nullable', 'exists:municipios,id'],
        ]);

        $datos  = [...$datos, ...$this->procedencia->exigir($datos)];
        $correo = mb_strtolower(trim($datos['correo']));

        // El código se emite ANTES de crear al cliente: si el límite de
        // reenvíos está agotado, no queremos dejar una cuenta a medias.
        $codigo = $this->verificacion->emitir($correo);

        Cliente::create([...$datos, 'correo' => $correo]);

        $this->verificacion->entregar($correo, $codigo);

        return redirect()
            ->route('portal.verificar', ['correo' => $correo])
            ->with('success', 'Te enviamos un código de 6 dígitos. Revisa tu correo.');
    }

    // ═══ Verificación del correo ═══

    public function mostrarVerificacion(Request $request)
    {
        return view('publico.auth.verificar', [
            'correo' => $request->query('correo', ''),
        ]);
    }

    public function verificar(Request $request)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email'],
            'codigo' => ['required', 'digits:6'],
        ]);

        $this->verificacion->validar($datos['correo'], $datos['codigo']);

        $cliente = Cliente::where('correo', mb_strtolower(trim($datos['correo'])))->firstOrFail();
        $cliente->update(['correo_verificado_en' => now()]);

        Auth::guard('cliente')->login($cliente);
        $request->session()->regenerate();

        return redirect()->route('compras.crear')
            ->with('success', 'Tu correo quedó verificado. Ya puedes comprar.');
    }

    public function reenviar(Request $request)
    {
        $datos = $request->validate(['correo' => ['required', 'email']]);
        $correo = mb_strtolower(trim($datos['correo']));

        $codigo = $this->verificacion->emitir($correo);
        $this->verificacion->entregar($correo, $codigo);

        return back()->with('success', 'Te enviamos un código nuevo.');
    }

    // ═══ Ingreso ═══

    public function mostrarIngreso()
    {
        return view('publico.auth.ingresar');
    }

    public function ingresar(Request $request)
    {
        $datos = $request->validate([
            'correo'   => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $correo  = mb_strtolower(trim($datos['correo']));
        $cliente = Cliente::where('correo', $correo)->first();

        // Cuenta creada por OAuth y sin contraseña local: no se puede entrar
        // por aquí, y decirlo evita que la persona crea que olvidó su clave.
        if ($cliente?->soloOauth()) {
            return back()->withErrors([
                'correo' => 'Esa cuenta se creó con Google. Ingresa con Google.',
            ])->withInput($request->only('correo'));
        }

        if (! Auth::guard('cliente')->attempt(['correo' => $correo, 'password' => $datos['password']])) {
            return back()->withErrors([
                'correo' => 'El correo o la contraseña no coinciden.',
            ])->withInput($request->only('correo'));
        }

        $cliente = Auth::guard('cliente')->user();

        // Correo sin verificar: se cierra la sesión y se manda a verificar.
        if (! $cliente->correoVerificado()) {
            Auth::guard('cliente')->logout();

            $codigo = $this->verificacion->emitir($correo);
            $this->verificacion->entregar($correo, $codigo);

            return redirect()->route('portal.verificar', ['correo' => $correo])
                ->with('error', 'Necesitas verificar tu correo. Te enviamos un código nuevo.');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('compras.crear'));
    }

    // ═══ Recuperar contraseña (2026-10-27) ═══

    public function mostrarRecuperar()
    {
        return view('publico.auth.recuperar');
    }

    /**
     * La respuesta es la misma exista o no la cuenta: así el formulario no
     * sirve para averiguar qué correos están registrados.
     */
    public function enviarEnlace(Request $request)
    {
        $datos = $request->validate(['correo' => ['required', 'email']]);

        PasswordBroker::broker('clientes')->sendResetLink(['correo' => mb_strtolower(trim($datos['correo']))]);

        return back()->with('success', 'Si el correo tiene una cuenta, te enviamos un enlace para cambiar tu contraseña. Revisa también spam.');
    }

    public function mostrarRestablecer(string $token)
    {
        return view('publico.auth.restablecer', ['token' => $token]);
    }

    public function restablecer(Request $request, string $token)
    {
        $datos = $request->validate([
            'correo'   => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        // Abrir el enlace prueba que el correo es suyo: si faltaba
        // verificarlo, queda verificado. Así una cuenta solo-Google también
        // puede crearse una contraseña.
        $estado = PasswordBroker::broker('clientes')->reset(
            ['correo' => mb_strtolower(trim($datos['correo'])), 'password' => $datos['password'], 'token' => $token],
            function (Cliente $cliente, string $password) {
                $cliente->forceFill([
                    'password'             => $password,
                    'remember_token'       => Str::random(60),
                    'correo_verificado_en' => $cliente->correo_verificado_en ?? now(),
                ])->save();
            },
        );

        if ($estado !== PasswordBroker::PASSWORD_RESET) {
            return back()->withErrors([
                'correo' => 'El enlace no es válido, ya venció o el correo no coincide. Pide uno nuevo.',
            ])->withInput($request->only('correo'));
        }

        return redirect()->route('portal.ingresar')->with('success', 'Listo, ya puedes ingresar con tu contraseña nueva.');
    }

    public function salir(Request $request)
    {
        Auth::guard('cliente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.inicio');
    }
}
