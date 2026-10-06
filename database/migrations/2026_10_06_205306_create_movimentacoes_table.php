<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->string('tipo', 30);

            // Com sinal: positiva entra, negativa sai.
            $table->decimal('quantidade', 12, 3);
            $table->decimal('saldo_anterior', 12, 3);
            $table->decimal('saldo_posterior', 12, 3);

            // Operação que gerou o movimento (entrada, venda, OS...): origem_type + origem_id.
            $table->nullableMorphs('origem');

            // Se esta linha for estorno de outra. O unique impede estornar duas vezes.
            $table->foreignId('estorno_de_id')->nullable()->unique()->constrained('movimentacoes')->restrictOnDelete();

            $table->text('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['produto_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes');
    }
};