<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('codigo_barras', 14)->nullable()->unique();
            $table->string('nome', 120);
            $table->text('descricao')->nullable();
            $table->string('categoria', 60)->nullable();
            $table->string('marca', 60)->nullable();
            $table->string('unidade', 6)->default('UN');

            $table->decimal('preco_custo', 12, 2)->default(0);
            $table->decimal('preco_venda', 12, 2);

            // Cache do saldo: só muda junto com uma movimentação (passo 7).
            $table->decimal('saldo', 12, 3)->default(0);
            $table->decimal('estoque_minimo', 12, 3)->default(0);

            // Dados fiscais para a NF-e / NFC-e.
            $table->string('ncm', 8)->nullable();
            $table->string('cfop', 4)->nullable();
            $table->string('cest', 7)->nullable();
            $table->unsignedTinyInteger('origem')->default(0);

            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('nome');
        });

        // No PostgreSQL, o próprio banco recusa saldo negativo.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE produtos ADD CONSTRAINT produtos_saldo_nao_negativo CHECK (saldo >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};