<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('cpf_cnpj', 14)->nullable()->unique(); // só dígitos
            $table->string('telefone', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('endereco', 200)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('nome');
        });

        Schema::create('motocicletas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('placa', 7)->unique(); // maiúsculas, sem hífen
            $table->string('marca', 40);
            $table->string('modelo', 60);
            $table->unsignedSmallInteger('ano')->nullable();
            $table->string('cor', 30)->nullable();
            $table->unsignedInteger('km_atual')->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motocicletas');
        Schema::dropIfExists('clientes');
    }
};