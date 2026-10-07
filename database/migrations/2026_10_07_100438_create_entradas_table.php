<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas', function (Blueprint $table) {
            $table->id();
            $table->string('fornecedor', 120)->nullable();
            $table->string('documento', 60)->nullable(); // nº da nota do fornecedor
            $table->date('data_entrada');
            $table->text('observacao')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('entrada_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrada_id')->constrained('entradas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('custo_unitario', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrada_itens');
        Schema::dropIfExists('entradas');
    }
};