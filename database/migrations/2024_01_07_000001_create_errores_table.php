<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de fallos del sistema.
 *
 * Tabla aparte de `movimientos` a propósito. Esa es la bitácora de auditoría
 * —quién cambió qué y cuándo, con cuenta responsable— y tiene valor de
 * rendición de cuentas. Un fallo no es la operación de nadie: casi siempre lo
 * produce un visitante anónimo, y el volumen de un bot escaneando rutas
 * sepultaría las entradas que de verdad importan.
 *
 * Se AGRUPA por `huella`: el mismo fallo repetido mil veces es una fila con
 * `ocurrencias = 1000`, no mil filas. Sin eso, una excepción en bucle llena
 * la base en minutos.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('errores', function (Blueprint $table) {
            $table->bigIncrements('id_error');

            // sha256 de clase + archivo + línea. Dos fallos con la misma
            // huella son el mismo problema aunque cambie el mensaje.
            $table->string('huella', 64)->unique();

            $table->string('clase', 190);
            $table->text('mensaje');
            $table->string('archivo', 255)->nullable();
            $table->unsignedInteger('linea')->nullable();

            $table->unsignedSmallInteger('codigo')->nullable();   // estado HTTP
            $table->string('metodo', 10)->nullable();
            $table->text('url')->nullable();

            // Quién lo encontró. Los dos pueden ser null: el fallo pudo
            // ocurrir en un job, o con un visitante sin sesión.
            $table->unsignedBigInteger('id_cuenta')->nullable();
            $table->uuid('id_cliente')->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('navegador')->nullable();

            $table->text('traza')->nullable();

            $table->unsignedInteger('ocurrencias')->default(1);
            $table->timestampTz('primera_vez')->useCurrent();
            $table->timestampTz('ultima_vez')->useCurrent();

            // Se marca a mano desde el panel cuando alguien ya lo atendió.
            $table->timestampTz('atendido_en')->nullable();
            $table->unsignedBigInteger('atendido_por')->nullable();

            // Orden natural de la pantalla: lo último que falló, primero.
            $table->index(['atendido_en', 'ultima_vez']);

            $table->foreign('id_cuenta')
                  ->references('id_cuenta')->on('cuentas')
                  ->onDelete('set null');

            $table->foreign('atendido_por')
                  ->references('id_cuenta')->on('cuentas')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errores');
    }
};
