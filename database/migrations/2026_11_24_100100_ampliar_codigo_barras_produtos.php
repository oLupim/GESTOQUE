<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de barras deixa de ser só EAN numérico (8–14 dígitos): etiquetas de peça usam
 * Code 39/128, que podem ser curtos e ter letras. A nota fiscal não usa este campo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('codigo_barras', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('codigo_barras', 14)->nullable()->change();
        });
    }
};
