<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Services\InterpretarEmbalagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regras que transformam o texto lido na foto da embalagem em nome, marca e categoria. */
class InterpretarEmbalagemTest extends TestCase
{
    use RefreshDatabase;

    private function interpretar(array $linhas): array
    {
        // [texto, altura da letra]
        return app(InterpretarEmbalagem::class)->interpretar(
            array_map(fn ($l) => ['texto' => $l[0], 'altura' => $l[1], 'confianca' => $l[2] ?? 90], $linhas)
        );
    }

    public function test_frasco_de_oleo(): void
    {
        $r = $this->interpretar([
            ['MOTUL', 80],
            ['5100 4T', 60],
            ['10W-40', 55],
            ['Óleo para motores 4 tempos', 20],
            ['Tecnologia Ester · 1L', 14],
            ['@#~~', 30, 20], // lixo com pouca confiança: ignorado
        ]);

        $this->assertSame('Motul', $r['marca']);
        $this->assertSame('Óleos e lubrificantes', $r['categoria']);
        // Opções com o texto como está na embalagem; a primeira (completa) já vai para o campo nome.
        $this->assertSame(['Óleo MOTUL 5100 4T 10W-40 1L', 'MOTUL 5100 4T', '5100 4T'], $r['opcoes']);
        $this->assertSame('Óleo MOTUL 5100 4T 10W-40 1L', $r['nome']);
    }

    public function test_linhas_reais_devolvidas_pelo_ocr(): void
    {
        // Saída real do Tesseract numa foto de teste: "4T" foi lido como "41" (erro típico de OCR).
        $r = app(InterpretarEmbalagem::class)->interpretar([
            ['texto' => 'MOTUL', 'altura' => 97, 'confianca' => 90],
            ['texto' => '5100 41', 'altura' => 66, 'confianca' => 78],
            ['texto' => '10W-40', 'altura' => 59, 'confianca' => 87],
            ['texto' => 'Óleo para motores 4 tempos', 'altura' => 44, 'confianca' => 96],
        ]);

        $this->assertSame('Motul', $r['marca']);
        $this->assertSame('Óleos e lubrificantes', $r['categoria']);
        // Em óleo, "41" (erro de leitura de "4T") é corrigido.
        $this->assertSame('Óleo MOTUL 5100 4T 10W-40', $r['nome']);
    }

    public function test_pastilha_de_freio_com_marca_ja_cadastrada(): void
    {
        Produto::factory()->create(['marca' => 'Cobreq']); // grafia do sistema tem prioridade

        $r = $this->interpretar([
            ['COBREQ', 70],
            ['PASTILHA DE FREIO', 50],
            ['Honda CG 160 Titan / Fan', 18],
            ['N-1042', 15],
        ]);

        $this->assertSame('Cobreq', $r['marca']);
        $this->assertSame('Freios', $r['categoria']);
        $this->assertSame([
            'PASTILHA DE FREIO Honda CG 160 Titan / Fan COBREQ',
            'COBREQ PASTILHA DE FREIO',
            'PASTILHA DE FREIO',
        ], $r['opcoes']);
        $this->assertContains('N-1042', $r['palavras']); // linhas lidas viram botões para montar o nome
    }

    public function test_vela_ngk_nao_confunde_com_honda_da_aplicacao(): void
    {
        $r = $this->interpretar([
            ['NGK', 90],
            ['VELA DE IGNIÇÃO', 40],
            ['CPR8EA-9', 35],
            ['Aplicação: Honda CB 300', 12],
        ]);

        $this->assertSame('NGK', $r['marca']); // a marca da linha maior ganha
        $this->assertSame('Velas de ignição', $r['categoria']);
    }

    public function test_corrige_marca_lida_errado_sem_estragar_palavras_parecidas(): void
    {
        $r = $this->interpretar([
            ['M0TUL', 80],          // zero no lugar do O
            ['MOTOR OIL 4T', 50],   // "MOTOR" não pode virar "MOTUL" (2 letras diferentes)
        ]);
        $this->assertSame('Motul', $r['marca']);
        $this->assertSame('MOTUL MOTOR OIL 4T', $r['opcoes'][1]);

        $r = $this->interpretar([['COBREO', 70], ['PASTILHA DE FREIO', 50]]); // uma letra errada
        $this->assertSame('Cobreq', $r['marca']);
        $this->assertSame('COBREQ PASTILHA DE FREIO', $r['opcoes'][1]);
    }

    public function test_foto_borrada_ainda_acha_marca_e_categoria(): void
    {
        // Foto real de celular: a marca veio com pouca certeza e só no "completo" (palavras duvidosas).
        $r = app(InterpretarEmbalagem::class)->interpretar([
            ['texto' => '', 'completo' => 'Castrol', 'altura' => 90, 'confianca' => 35],
            ['texto' => 'POWER1 4T', 'completo' => 'POWER1 4T', 'altura' => 60, 'confianca' => 80],
            ['texto' => 'LUBRIFICANTES', 'completo' => 'LUBRIFICANTES', 'altura' => 20, 'confianca' => 70], // plural
        ]);

        $this->assertSame('Castrol', $r['marca']);
        $this->assertSame('Óleos e lubrificantes', $r['categoria']);
        $this->assertStringContainsString('POWER1 4T', $r['nome']);
        $this->assertStringContainsString('Castrol', $r['nome']); // marca entra no nome mesmo vindo de linha borrada
    }

    public function test_categoria_com_plural_numero_no_lugar_de_letra_e_letra_errada(): void
    {
        $this->assertSame('Filtros', $this->interpretar([['FILTROS DE AR', 40]])['categoria']);
        $this->assertSame('Óleos e lubrificantes', $this->interpretar([['0LEO PARA MOTO', 40]])['categoria']);
        $this->assertSame('Freios', $this->interpretar([['PASTILNA DIANTEIRA', 40]])['categoria']);
        $this->assertNull($this->interpretar([['Fabricado no Brasil', 40]])['categoria']); // nada parecido
    }

    public function test_texto_sem_pistas_nao_inventa(): void
    {
        $r = $this->interpretar([['Lote 2231', 10], ['Fabricado no Brasil', 9]]);

        $this->assertNull($r['marca']);
        $this->assertNull($r['categoria']);
    }

    public function test_rota_valida_e_devolve_json(): void
    {
        $this->postJson('/produtos/interpretar-embalagem', ['linhas' => [['texto' => 'PNEU PIRELLI SUPER CITY', 'altura' => 40]]])
            ->assertOk()
            ->assertJson(['marca' => 'Pirelli', 'categoria' => 'Pneus']);

        $this->postJson('/produtos/interpretar-embalagem', ['linhas' => 'nada'])->assertStatus(422);
    }
}
