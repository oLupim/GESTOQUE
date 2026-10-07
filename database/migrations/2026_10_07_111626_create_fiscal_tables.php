<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha só: os dados fiscais da empresa emitente.
        Schema::create('configuracao_fiscal', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social', 120)->nullable();
            $table->string('cnpj', 14)->nullable();
            $table->string('inscricao_estadual', 20)->nullable();
            $table->unsignedTinyInteger('crt')->default(1);      // 1 = Simples Nacional, 4 = MEI, 3 = Regime normal
            $table->unsignedTinyInteger('ambiente')->default(2); // 2 = homologação, 1 = produção
            $table->text('api_token')->nullable();               // guardado criptografado
            $table->timestamps();
        });

        Schema::create('documentos_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->restrictOnDelete();
            $table->unsignedTinyInteger('modelo');   // 65 = NFC-e, 55 = NF-e
            $table->unsignedTinyInteger('ambiente');
            $table->string('status', 20)->default('pendente');
            $table->unsignedInteger('numero')->nullable();
            $table->unsignedSmallInteger('serie')->nullable();
            $table->string('chave', 44)->nullable()->unique();
            $table->string('protocolo', 30)->nullable();
            $table->unsignedSmallInteger('codigo_sefaz')->nullable();
            $table->text('mensagem')->nullable();
            $table->unsignedSmallInteger('tentativas')->default(0);
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('ultima_resposta')->nullable(); // auditoria do retorno da API
            $table->timestamp('autorizada_em')->nullable();
            $table->timestamps();

            // Um documento por venda e modelo: reenviar usa o mesmo registro.
            $table->unique(['venda_id', 'modelo']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_fiscais');
        Schema::dropIfExists('configuracao_fiscal');
    }
};