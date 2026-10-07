<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('confirmada'); // confirmada | cancelada
            $table->string('forma_pagamento', 20);                // pix | dinheiro | debito | credito
            $table->decimal('subtotal', 12, 2);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelada_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('quantidade', 12, 3);
            // Cópias congeladas no momento da venda (preço histórico e custo para margem).
            $table->decimal('preco_unitario', 12, 2);
            $table->decimal('custo_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');
    }
};