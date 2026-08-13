<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos (brief §5.5).
 *
 * `referencia_externa` es UNIQUE y funciona como llave de idempotencia: los
 * bancos reenvían el mismo webhook varias veces, y el índice único es lo que
 * garantiza que una compra no se marque pagada dos veces (§4.4).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_compra')->constrained('compras')->restrictOnDelete();

            $table->string('proveedor', 40);
            $table->string('referencia_externa', 120)->unique();
            $table->integer('monto_centavos');
            $table->string('estado', 20);              // iniciado | aprobado | rechazado | reembolsado
            $table->string('autorizacion', 80)->nullable();
            $table->json('payload_webhook')->nullable();
            $table->timestampTz('conciliado_en')->nullable();

            $table->timestampsTz();

            $table->index(['id_compra', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
