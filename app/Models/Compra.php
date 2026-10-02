<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Compra de boletos (brief §5.4).
 *
 * Estados y transiciones válidas (§5.7):
 *
 *   pendiente_pago  → pagada | expirada
 *   pagada          → acceso_parcial | utilizada | vencida | cancelada
 *                   → pagada           (reagendar)
 *   acceso_parcial  → utilizada
 *   cancelada       → reembolsada
 */
class Compra extends Model
{
    use SoftDeletes;

    public const PENDIENTE_PAGO  = 'pendiente_pago';
    public const PAGADA          = 'pagada';
    public const EXPIRADA        = 'expirada';
    public const ACCESO_PARCIAL  = 'acceso_parcial';
    public const UTILIZADA       = 'utilizada';
    public const VENCIDA         = 'vencida';
    public const CANCELADA       = 'cancelada';
    public const REEMBOLSADA     = 'reembolsada';

    /** Minutos que una compra puede quedarse sin pagar antes de expirar (§5.7). */
    public const MINUTOS_PARA_EXPIRAR = 15;

    private const TRANSICIONES = [
        self::PENDIENTE_PAGO => [self::PAGADA, self::EXPIRADA],
        self::PAGADA         => [self::ACCESO_PARCIAL, self::UTILIZADA, self::VENCIDA, self::CANCELADA, self::PAGADA],
        self::ACCESO_PARCIAL => [self::UTILIZADA, self::VENCIDA],
        self::CANCELADA      => [self::REEMBOLSADA],
        self::EXPIRADA       => [],
        self::UTILIZADA      => [],
        self::VENCIDA        => [],
        self::REEMBOLSADA    => [],
    ];

    protected $table = 'compras';

    protected $fillable = [
        'folio', 'id_cliente', 'correo_invitado', 'fecha_compra', 'fecha_visita', 'total_centavos',
        'pases_total', 'pases_usados', 'estado', 'qr_token', 'qr_expira_en',
        'id_promocion', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra'   => 'datetime',
            'fecha_visita'   => 'date',
            'total_centavos' => 'integer',
            'pases_total'    => 'integer',
            'pases_usados'   => 'integer',
            'qr_expira_en'   => 'datetime',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function detalle()
    {
        return $this->hasMany(CompraDetalle::class, 'id_compra');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_compra');
    }

    // ═══ Quién compró ═══
    //
    // Una compra hecha como invitado no tiene sesión detrás: se identifica
    // por el correo que la persona verificó, y se consulta con un enlace
    // firmado en vez de «Mis compras». Si ese correo ya tenía cuenta, además
    // queda ligada a ella (`id_cliente`), pero sigue siendo de invitado.

    public function esDeInvitado(): bool
    {
        return $this->correo_invitado !== null;
    }

    /** A dónde se mandan los boletos. */
    public function correoDestino(): string
    {
        return $this->correo_invitado ?? $this->cliente->correo;
    }

    /** El nombre de quien compró, si se conoce: al invitado no se le pide. */
    public function nombreComprador(): ?string
    {
        return $this->cliente?->nombreCompleto();
    }

    /**
     * Las tres pantallas del comprador sobre su compra. Con cuenta van por
     * las rutas con sesión; como invitado, por su equivalente firmado: la
     * firma es lo que prueba que el enlace salió del sistema, porque el
     * folio es consecutivo y se adivina.
     */
    public function urlDetalle(): string
    {
        return $this->urlDelComprador('ver');
    }

    public function urlQr(): string
    {
        return $this->urlDelComprador('qr');
    }

    public function urlRetorno(): string
    {
        return $this->urlDelComprador('retorno');
    }

    private function urlDelComprador(string $pantalla): string
    {
        return $this->esDeInvitado()
            ? URL::signedRoute("invitado.{$pantalla}", ['folio' => $this->folio])
            : route("compras.{$pantalla}", ['folio' => $this->folio]);
    }

    public function puedeTransicionarA(string $estado): bool
    {
        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    public function estaPagada(): bool
    {
        return in_array($this->estado, [self::PAGADA, self::ACCESO_PARCIAL], true);
    }

    public function pasesDisponibles(): int
    {
        return max(0, $this->pases_total - $this->pases_usados);
    }

    public function totalFormateado(): string
    {
        return '$' . number_format($this->total_centavos / 100, 2);
    }

    /**
     * Consume pases de forma atómica (brief §4.5).
     *
     * Una sola sentencia condicional: sin esto, dos escaneos simultáneos del
     * mismo QR meterían al doble de gente.
     */
    public static function consumirPases(int $idCompra, int $pases): bool
    {
        $filas = DB::table('compras')
            ->where('id', $idCompra)
            ->whereIn('estado', [self::PAGADA, self::ACCESO_PARCIAL])
            ->whereNull('deleted_at')
            ->whereRaw('pases_usados + ? <= pases_total', [$pases])
            ->update([
                'pases_usados' => DB::raw("pases_usados + {$pases}"),
                'updated_at'   => now(),
            ]);

        return $filas > 0;
    }

    public function scopePendientesVencidas($query)
    {
        return $query->where('estado', self::PENDIENTE_PAGO)
            ->where('fecha_compra', '<=', now()->subMinutes(self::MINUTOS_PARA_EXPIRAR));
    }
}
