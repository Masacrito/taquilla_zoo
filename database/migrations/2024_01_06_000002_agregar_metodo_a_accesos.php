<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo se identificó el pase: leyendo el QR o capturando el folio a mano.
 *
 * Importa para la auditoría: una entrada autorizada por folio se apoya en el
 * criterio del operador, no en la firma criptográfica del código. Si algún
 * día hay una queja, hay que poder distinguir las dos cosas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('accesos', function (Blueprint $table) {
            $table->string('metodo', 10)->default('qr')->after('id_torniquete');
        });
    }

    public function down(): void
    {
        Schema::table('accesos', function (Blueprint $table) {
            $table->dropColumn('metodo');
        });
    }
};
