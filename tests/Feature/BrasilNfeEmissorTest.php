<?php

namespace Tests\Feature;

use App\Fiscal\BrasilNfeEmissor;
use App\Models\ConfiguracaoFiscal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrasilNfeEmissorTest extends TestCase
{
    private function nota(): array
    {
        return [
            'modelo' => 65, 'forma_pagamento' => 'pix', 'total' => 95, 'desconto' => 5,
            'itens' => [[
                'codigo' => 'OLE-001', 'nome' => 'Óleo Motul 10W40', 'ncm' => '27101932', 'cfop' => '5102',
                'unidade' => 'UN', 'origem' => 0, 'quantidade' => 2, 'valor_unitario' => 50, 'valor_total' => 100,
            ]],
        ];
    }

    private function config(): ConfiguracaoFiscal
    {
        return new ConfiguracaoFiscal(['ambiente' => 2, 'crt' => 1, 'api_token' => 'TOKEN']);
    }

    public function test_monta_payload_no_formato_da_documentacao(): void
    {
        Http::fake(['*' => Http::response(['ReturnNF' => ['Ok' => true, 'NumeroProtocolo' => '135'], 'Error' => ''])]);

        $r = (new BrasilNfeEmissor)->emitir($this->nota(), $this->config());

        $this->assertTrue($r->autorizada);
        $this->assertSame('135', $r->protocolo);

        Http::assertSent(fn (Request $req) => $req->hasHeader('Token', 'TOKEN')
            && $req['ValorDesconto'] == 5
            && $req['Pagamentos'][0]['FormaPagamento'] === 17
            && $req['Pagamentos'][0]['VlPago'] == 95
            && $req['Produtos'][0]['UnidadeComercial'] === 'UN'
            && $req['Produtos'][0]['Imposto']['ICMS']['CodSituacaoTributaria'] === '102');
    }

    public function test_mensagem_vem_do_campo_error(): void
    {
        Http::fake(['*' => Http::response([
            'ReturnNF' => ['Ok' => false, 'Numero' => 0, 'DsStatusRespostaSefaz' => null],
            'Error' => 'Certificado digital não encontrado.',
            'Avisos' => ['CSC não informado'],
        ])]);

        $r = (new BrasilNfeEmissor)->emitir($this->nota(), $this->config());

        $this->assertFalse($r->autorizada);
        $this->assertNull($r->numero);
        $this->assertSame('Certificado digital não encontrado. | Avisos: CSC não informado', $r->mensagem);
        $this->assertArrayHasKey('Error', $r->bruto);
    }
}