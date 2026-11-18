<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O perfil "balconista" deixou de existir: ficam só administrador e mecânico.
 * Quem era balconista vira mecânico, e o padrão da coluna passa a ser "mecanico".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('perfil', 'balconista')->update(['perfil' => 'mecanico']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('perfil', 20)->default('mecanico')->change(); // administrador | mecanico
        });
    }

    public function down(): void
    {
        // Só o padrão volta; não dá para saber quem era balconista antes.
        Schema::table('users', function (Blueprint $table) {
            $table->string('perfil', 20)->default('balconista')->change();
        });
    }
};
