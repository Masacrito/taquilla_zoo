<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo cambió su contraseña cada cuenta interna.
 *
 * NULL significa que sigue con la que se le asignó: la inicial del seeder en
 * el caso del Super Admin, o la que le puso quien creó la cuenta. El tablero
 * usa ese dato para avisar mientras el Super Admin no la haya cambiado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cuentas', function (Blueprint $table) {
            $table->timestampTz('password_cambiado_en')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas', function (Blueprint $table) {
            $table->dropColumn('password_cambiado_en');
        });
    }
};
