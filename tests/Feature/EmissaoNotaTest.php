<?php

namespace Tests\Feature;

use App\Actions\ConfirmarVenda;
use App\Actions\EmitirNotaFiscal;
use App\Fiscal\BrasilNfeEmissor;
use App\Models\ConfiguracaoFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Movimentacao;
use App\Models\Produto;
use App\Models\Venda;
use App\Services\EstoqueService;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmissaoNotaTest extends TestCase
{
    use RefreshDatabase;

    private Venda $venda;
    private Produto $oleo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        ConfiguracaoFiscal::atual()->update(['cnpj' => '12345678000199', 'api_token' => 'token-teste']);

        $this->oleo = Produto::factory()->create(['preco_venda' => 65, 'ncm' => '27101932', 'cfop' => '5102']);
        app(EstoqueService::class)->entrar($this->oleo, 10);
        $this->venda = app(ConfirmarVenda::class)->executar([
            'forma_pagamento' => 'pix',
            'itens' => [['produto_id' => $this->oleo->id, 'quantidade' => 2]],
        ]);
    }

    private function autorizada(): array
    {
        return [
            'ReturnNF' => [
                'Numero' => 1, 'Serie' => 1, 'ChaveNF' => str_repeat('4', 44),
                'CodStatusRespostaSefaz' => 100, 'DsStatusRespostaSefaz' => 'Autorizado o uso da NF-e', 'Ok' => true,
            ],
            'Base64Xml' => base64_encode('<nfeProc/>'),
            'Base64File' => base64_encode('%PDF-1.4'),
        ];
    }

    private function rejeitada(): array
    {
        return ['ReturnNF' => ['CodStatusRespostaSefaz' => 778, 'DsStatusRespostaSefaz' => 'Rejeição: NCM inexistente', 'Ok' => false]];
    }

    public function test_autoriza_e_guarda_chave_xml_e_danfe(): void
    {
        Http::fake([BrasilNfeEmissor::URL => Http::response($this->autorizada())]);

        $doc = app(EmitirNotaFiscal::class)->executar($this->venda);

        $this->assertSame(DocumentoFiscal::AUTORIZADA, $doc->status);
        $this->assertSame(str_repeat('4', 44), $doc->chave);
        Storage::assertExists($doc->xml_path);
        Storage::assertExists($doc->pdf_path);

        // Conferindo o que foi enviado para a Brasil NFe.
        Http::assertSent(fn (Request $r) => $r->hasHeader('Token', 'token-teste')
            && $r['TipoAmbiente'] === 2
            && $r['ModeloDocumento'] === 65
            && $r['Produtos'][0]['NCM'] === '27101932'
            && $r['Produtos'][0]['ValorUnitario'] === 65.0
            && $r['Pagamentos'][0]['TipoPagamento'] === 17
            && $r['Pagamentos'][0]['Valor'] === 130.0);
    }

    public function test_rejeitada_pode_ser_reenviada_no_mesmo_documento_sem_mexer_no_estoque(): void
    {
        $movimentacoesAntes = Movimentacao::count();

        Http::fakeSequence(BrasilNfeEmissor::URL)
            ->push($this->rejeitada())
            ->push($this->autorizada());

        $doc = app(EmitirNotaFiscal::class)->executar($this->venda);
        $this->assertSame(DocumentoFiscal::REJEITADA, $doc->status);
        $this->assertSame(778, $doc->codigo_sefaz);
        $this->assertStringContainsString('NCM', $doc->mensagem);

        $doc = app(EmitirNotaFiscal::class)->executar($this->venda);
        $this->assertSame(DocumentoFiscal::AUTORIZADA, $doc->status);
        $this->assertSame(2, $doc->tentativas);

        $this->assertSame(1, DocumentoFiscal::count());                // mesmo documento
        $this->assertSame($movimentacoesAntes, Movimentacao::count()); // nenhuma baixa nova
        $this->assertEquals(8, (float) $this->oleo->fresh()->saldo);
    }

    public function test_nota_autorizada_nao_e_emitida_de_novo(): void
    {
        Http::fake([BrasilNfeEmissor::URL => Http::response($this->autorizada())]);
        app(EmitirNotaFiscal::class)->executar($this->venda);

        $this->expectException(DomainException::class);
        try {
            app(EmitirNotaFiscal::class)->executar($this->venda);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_produto_sem_ncm_e_barrado_antes_de_enviar(): void
    {
        Http::fake();
        $this->oleo->update(['ncm' => null]);

        try {
            app(EmitirNotaFiscal::class)->executar($this->venda);
            $this->fail('Deveria barrar produto sem NCM.');
        } catch (DomainException $e) {
            $this->assertStringContainsString($this->oleo->nome, $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_falha_de_comunicacao_marca_erro_e_permite_tentar_de_novo(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $doc = app(EmitirNotaFiscal::class)->executar($this->venda);

        $this->assertSame(DocumentoFiscal::ERRO, $doc->status);
        $this->assertTrue($doc->podeEnviar());
    }

    public function test_sem_configuracao_nao_emite(): void
    {
        Http::fake();
        ConfiguracaoFiscal::atual()->update(['api_token' => null]);

        $this->expectException(DomainException::class);
        app(EmitirNotaFiscal::class)->executar($this->venda);
    }

    public function test_venda_cancelada_nao_emite(): void
    {
        Http::fake();
        $this->venda->update(['status' => Venda::CANCELADA]);

        $this->expectException(DomainException::class);
        app(EmitirNotaFiscal::class)->executar($this->venda->fresh());
    }
}