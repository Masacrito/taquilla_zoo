<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compras y su detalle (brief §5.4).
 *
 * `total_centavos` e `importe_centavos` son INTEGER (§4.1).
 * `precio_centavos_snap` y `rubro_nombre_snap` congelan la tarifa al momento
 * de comprar (§4.3): cambiar un precio hoy no altera un corte de ayer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique();
            $table->foreignUuid('id_cliente')->constrained('clientes')->restrictOnDelete();

            $table->timestampTz('fecha_compra')->useCurrent();
            $table->date('fecha_visita');

            $table->integer('total_centavos');
            $table->integer('pases_total');
            $table->integer('pases_usados')->default(0);

            $table->string('estado', 20)->default('pendiente_pago');

            $table->string('qr_token', 128)->nullable()->unique();
            $table->timestampTz('qr_expira_en')->nullable();

            $table->foreignId('id_promocion')->nullable()->constrained('promociones')->nullOnDelete();
            $table->text('observaciones')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['estado', 'fecha_compra']);
            $table->index('fecha_visita');
        });

        Schema::create('compra_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_compra')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('id_rubro')->constrained('rubros')->restrictOnDelete();

            // Congelados al comprar. NO son redundancia: son el requisito de
            // auditoría del §4.3.
            $table->string('rubro_nombre_snap', 80);
            $table->integer('precio_centavos_snap');

            $table->integer('cant_hombre')->default(0);
            $table->integer('cant_mujer')->default(0);
            $table->integer('cantidad');
            $table->integer('importe_centavos');

            // Procedencia POR RENGLÓN: un mismo comprador puede traer gente
            // de distintos lugares (§5.4).
            $table->foreignId('id_pais')->nullable()->constrained('paises')->nullOnDelete();
            $table->foreignId('id_estado')->nullable()->constrained('estados')->nullOnDelete();
            $table->foreignId('id_municipio')->nullable()->constrained('municipios')->nullOnDelete();

            $table->foreignId('id_nacionalidad')->constrained('nacionalidades')->restrictOnDelete();
            $table->foreignId('id_subnacionalidad')->constrained('subnacionalidades')->restrictOnDelete();
            $table->foreignId('id_tipo_acceso')->constrained('tipos_acceso')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE compras ADD CONSTRAINT compras_pases_usados_validos CHECK (pases_usados >= 0 AND pases_usados <= pases_total)');
            DB::statement('ALTER TABLE compras ADD CONSTRAINT compras_total_no_negativo CHECK (total_centavos >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalle');
        Schema::dropIfExists('compras');
    }
};
