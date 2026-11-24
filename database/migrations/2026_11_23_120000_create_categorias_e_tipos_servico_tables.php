<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias padrão (produtos e serviços) e lista de serviços padrão com código interno.
 * Os dados entram pela própria migração (e não pelo seeder) para existirem também em produção.
 */
return new class extends Migration
{
    // NCM de "partes e acessórios de motocicletas". Sugestão: o contador deve confirmar.
    private const PECAS_MOTO = '87141000';

    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->string('tipo', 10); // produto | servico
            // Prefixo do código interno (OLE → OLE-001). Só para produtos.
            $table->string('prefixo', 5)->nullable();
            // Sugestões que preenchem o cadastro do produto ao escolher a categoria.
            $table->string('unidade', 5)->nullable();
            $table->string('ncm', 8)->nullable();
            $table->string('cfop', 4)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tipo', 'nome']);
            $table->unique(['tipo', 'prefixo']);
        });

        Schema::create('tipos_servico', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // SRV-001
            $table->string('nome', 120);
            $table->string('categoria', 60)->nullable();
            $table->decimal('valor_sugerido', 12, 2)->nullable(); // vazio = a oficina define na hora
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        $agora = now();

        // [nome, prefixo, unidade, ncm]  · CFOP 5102 = venda de mercadoria comprada para revenda.
        $produtos = [
            ['Óleos e lubrificantes', 'OLE', 'UN', '27101932'],
            ['Fluidos e aditivos', 'FLU', 'UN', null],
            ['Filtros', 'FIL', 'UN', null],
            ['Freios', 'FRE', 'UN', self::PECAS_MOTO],
            ['Transmissão', 'TRA', 'UN', self::PECAS_MOTO],
            ['Pneus', 'PNE', 'UN', '40114000'],
            ['Câmaras de ar', 'CAM', 'UN', '40139000'],
            ['Velas de ignição', 'VEL', 'UN', '85111000'],
            ['Baterias', 'BAT', 'UN', '85071000'],
            ['Elétrica e iluminação', 'ELE', 'UN', null],
            ['Motor', 'MOT', 'UN', self::PECAS_MOTO],
            ['Suspensão', 'SUS', 'UN', self::PECAS_MOTO],
            ['Cabos', 'CAB', 'UN', self::PECAS_MOTO],
            ['Rolamentos', 'ROL', 'UN', '84821010'],
            ['Carenagem e plásticos', 'CAR', 'UN', self::PECAS_MOTO],
            ['Parafusos e fixadores', 'FIX', 'UN', null],
            ['Acessórios', 'ACE', 'UN', null],
            ['Diversos', 'DIV', 'UN', null],
        ];

        DB::table('categorias')->insert(array_map(fn ($c) => [
            'nome' => $c[0], 'tipo' => 'produto', 'prefixo' => $c[1], 'unidade' => $c[2],
            'ncm' => $c[3], 'cfop' => '5102', 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ], $produtos));

        $servicos = [
            'Revisão' => ['Revisão geral', 'Diagnóstico / avaliação', 'Troca de óleo', 'Troca de filtro de ar', 'Troca de filtro de óleo'],
            'Motor' => ['Limpeza e regulagem de carburador', 'Limpeza de bicos injetores', 'Troca de vela', 'Regulagem de válvulas', 'Retífica / revisão de motor'],
            'Freios' => ['Troca de pastilhas de freio', 'Troca de lonas de freio', 'Troca de fluido e sangria do freio', 'Regulagem de freio'],
            'Transmissão' => ['Troca de kit relação', 'Lubrificação e regulagem de corrente', 'Troca de cabo de embreagem', 'Regulagem de embreagem'],
            'Pneus e rodas' => ['Troca de pneu', 'Conserto de câmara de ar', 'Troca de rolamentos de roda', 'Alinhamento de roda'],
            'Elétrica' => ['Revisão elétrica', 'Troca de bateria', 'Troca de lâmpada', 'Instalação de acessório elétrico'],
            'Suspensão' => ['Troca de óleo e retentores da bengala', 'Revisão da suspensão traseira'],
        ];

        DB::table('categorias')->insert(array_map(fn ($nome) => [
            'nome' => $nome, 'tipo' => 'servico', 'prefixo' => null, 'unidade' => null,
            'ncm' => null, 'cfop' => null, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ], array_keys($servicos)));

        $linhas = [];
        $n = 1;
        foreach ($servicos as $categoria => $nomes) {
            foreach ($nomes as $nome) {
                $linhas[] = [
                    'codigo' => sprintf('SRV-%03d', $n++), 'nome' => $nome, 'categoria' => $categoria,
                    'valor_sugerido' => null, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
                ];
            }
        }
        DB::table('tipos_servico')->insert($linhas);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_servico');
        Schema::dropIfExists('categorias');
    }
};
