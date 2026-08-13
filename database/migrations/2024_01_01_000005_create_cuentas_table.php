<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cuentas', function (Blueprint $table) {
            $table->bigIncrements('id_cuenta');
            $table->string('username', 60)->unique();
            $table->string('password', 255);
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_rol');
            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();   // brief §4.6: nada se borra

            $table->foreign('id_usuario')
                  ->references('id_usuario')->on('usuarios')
                  ->onDelete('cascade');

            $table->foreign('id_rol')
                  ->references('id_rol')->on('roles')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas');
    }
};
