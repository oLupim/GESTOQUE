<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('motocicleta_id')->constrained('motocicletas')->restrictOnDelete();
            $table->string('status', 20)->default('aberta');
            $table->string('mecanico', 60)->nullable();
            $table->unsignedInteger('km_entrada')->nullable();
            $table->text('problema')->nullable();          // o que o cliente relatou
            $table->string('descricao_servico', 200)->nullable(); // mão de obra executada
            $table->decimal('valor_mao_obra', 12, 2)->default(0);
            $table->decimal('valor_pecas', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('finalizada_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('os_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('preco_unitario', 12, 2);
            $table->decimal('custo_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            // A saída de estoque gerada por esta peça: é ela que se estorna ao remover.
            $table->foreignId('movimentacao_id')->nullable()->constrained('movimentacoes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_itens');
        Schema::dropIfExists('ordens_servico');
    }
};