<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de accesos y reagendas (brief §5.6).
 *
 * `accesos` registra TODO escaneo, incluidos los rechazados: saber cuántos
 * QR inválidos se presentaron es parte de la auditoría, y sin ese registro
 * no hay forma de investigar una queja en el torniquete.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $table) {
            $table->id();

            // Nullable: un QR falsificado no corresponde a ninguna compra,
            // pero el intento igual debe quedar registrado.
            $table->foreignId('id_compra')->nullable()->constrained('compras')->nullOnDelete();

            $table->string('id_torniquete', 40);
            $table->integer('pases_consumidos')->default(0);
            $table->timestampTz('escaneado_en')->useCurrent();

            // Operador que escaneó. Null si el torniquete opera desatendido.
            $table->unsignedBigInteger('id_cuenta')->nullable();

            $table->string('resultado', 20);              // permitido | rechazado
            $table->string('motivo_rechazo', 120)->nullable();

            $table->foreign('id_cuenta')
                  ->references('id_cuenta')->on('cuentas')
                  ->nullOnDelete();

            $table->index(['escaneado_en', 'resultado']);
            $table->index('id_compra');
        });

        Schema::create('reagendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_compra')->constrained('compras')->cascadeOnDelete();
            $table->date('fecha_anterior');
            $table->date('fecha_nueva');
            $table->string('motivo', 200)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reagendas');
        Schema::dropIfExists('accesos');
    }
};
