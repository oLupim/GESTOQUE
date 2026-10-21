<?php

namespace Tests\Feature;

use App\Models\DocumentoFiscal;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FiscalTelaTest extends TestCase
{
    use RefreshDatabase;

    private function venda(string $status = Venda::CONFIRMADA): Venda
    {
        return Venda::forceCreate([
            'status' => $status, 'forma_pagamento' => 'pix',
            'subtotal' => 50, 'desconto' => 0, 'total' => 50, 'user_id' => auth()->id(),
        ]);
    }

    public function test_tela_fiscal_lista_documentos(): void
    {
        DocumentoFiscal::forceCreate([
            'venda_id' => $this->venda()->id, 'modelo' => 65, 'ambiente' => 2,
            'status' => 'rejeitada', 'mensagem' => 'NCM inválido', 'tentativas' => 1,
        ]);

        $this->withoutVite()->get('/fiscal')->assertInertia(fn (Assert $p) => $p
            ->component('Fiscal/Index', false)
            ->has('documentos.data', 1)
            ->where('documentos.data.0.mensagem', 'NCM inválido')
            ->where('contagem.rejeitada', 1));
    }

    public function test_venda_cancelada_mostra_erro_e_nao_chama_api(): void
    {
        Http::preventStrayRequests();

        $this->post('/vendas/'.$this->venda(Venda::CANCELADA)->id.'/nfce')
            ->assertSessionHas('error');

        $this->assertSame(0, DocumentoFiscal::where('status', 'autorizada')->count());
    }

    public function test_download_do_xml(): void
    {
        Storage::fake();
        Storage::put('fiscal/1/chave.xml', '<nfe/>');

        $doc = DocumentoFiscal::forceCreate([
            'venda_id' => $this->venda()->id, 'modelo' => 65, 'ambiente' => 2,
            'status' => 'autorizada', 'tentativas' => 1, 'xml_path' => 'fiscal/1/chave.xml',
        ]);

        $this->get("/fiscal/{$doc->id}/xml")->assertOk()->assertDownload();
        $this->get("/fiscal/{$doc->id}/pdf")->assertNotFound();
    }
}