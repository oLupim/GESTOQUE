<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dados da oficina (nome, logo, WhatsApp e endereço) passam a ficar num registro único,
 * editado pelo administrador em Configurações → Empresa.
 * Antes ficavam em cada usuário (users.empresa / users.foto_path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oficina', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('whatsapp', 13)->nullable(); // só dígitos: 55 + DDD + número
            $table->string('endereco', 200)->nullable();
            $table->timestamps();
        });

        // Aproveita o nome e o logo que o administrador já tinha salvo.
        $origem = DB::table('users')
            ->where('perfil', 'administrador')
            ->where(fn ($q) => $q->whereNotNull('empresa')->orWhereNotNull('foto_path'))
            ->orderBy('id')
            ->first();

        DB::table('oficina')->insert([
            'nome' => $origem?->empresa,
            'logo_path' => $origem?->foto_path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['empresa', 'foto_path']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('empresa', 100)->nullable()->after('name');
            $table->string('foto_path')->nullable();
        });

        $oficina = DB::table('oficina')->first();
        $admin = DB::table('users')->where('perfil', 'administrador')->orderBy('id')->first();

        if ($oficina && $admin) {
            DB::table('users')->where('id', $admin->id)
                ->update(['empresa' => $oficina->nome, 'foto_path' => $oficina->logo_path]);
        }

        Schema::dropIfExists('oficina');
    }
};
