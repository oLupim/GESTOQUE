<?php

namespace App\Actions;

use App\Fiscal\EmissorFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Venda;
use App\Models\VendaItem;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Emite a NFC-e de uma venda já confirmada.
 * Nunca mexe no estoque: a baixa aconteceu na confirmação da venda.
 */
class EmitirNotaFiscal
{
    public function __construct(private EmissorFiscal $emissor) {}

    public function executar(Venda $venda): DocumentoFiscal
    {
        $config = ConfiguracaoFiscal::atual();
        $this->validar($venda, $config);

        // 1) Reserva o envio numa transação curta (sem segurar o banco durante a chamada HTTP).
        $doc = DB::transaction(function () use ($venda, $config) {
            $doc = DocumentoFiscal::firstOrCreate(
                ['venda_id' => $venda->id, 'modelo' => DocumentoFiscal::NFCE],
                ['ambiente' => $config->ambiente, 'status' => DocumentoFiscal::PENDENTE],
            );
            $doc = DocumentoFiscal::whereKey($doc->id)->lockForUpdate()->first();

            if ($doc->status === DocumentoFiscal::AUTORIZADA) {
                throw new DomainException("A NFC-e da venda #{$venda->id} já está autorizada.");
            }
            if (! $doc->podeEnviar()) {
                throw new DomainException('Esta nota já está sendo enviada. Aguarde alguns segundos.');
            }

            $doc->update(['status' => DocumentoFiscal::PROCESSANDO, 'tentativas' => $doc->tentativas + 1]);

            return $doc;
        });

        // 2) Chama o provedor.
        try {
            $r = $this->emissor->emitir($this->montarNota($venda), $config);
        } catch (ConnectionException $e) {
            $doc->update(['status' => DocumentoFiscal::ERRO, 'mensagem' => 'Sem comunicação com o serviço fiscal. Tente novamente.']);

            return $doc;
        }

        // 3) Registra o resultado.
        if (! $r->autorizada) {
            $doc->update([
                'status' => DocumentoFiscal::REJEITADA,
                'codigo_sefaz' => $r->codigo,
                'mensagem' => $r->mensagem,
                'ultima_resposta' => $r->bruto,
            ]);

            return $doc;
        }

        $pasta = "fiscal/{$venda->id}";
        $nome = $r->chave ?? "venda-{$venda->id}";
        if ($r->xml) Storage::put("{$pasta}/{$nome}.xml", $r->xml);
        if ($r->pdf) Storage::put("{$pasta}/{$nome}.pdf", $r->pdf);

        $doc->update([
            'status' => DocumentoFiscal::AUTORIZADA,
            'chave' => $r->chave,
            'numero' => $r->numero,
            'serie' => $r->serie,
            'protocolo' => $r->protocolo,
            'codigo_sefaz' => $r->codigo,
            'mensagem' => $r->mensagem,
            'xml_path' => $r->xml ? "{$pasta}/{$nome}.xml" : null,
            'pdf_path' => $r->pdf ? "{$pasta}/{$nome}.pdf" : null,
            'ultima_resposta' => $r->bruto,
            'autorizada_em' => now(),
        ]);

        return $doc;
    }

    /** Barra antes de enviar o que a SEFAZ certamente rejeitaria. */
    private function validar(Venda $venda, ConfiguracaoFiscal $config): void
    {
        if ($venda->estaCancelada()) {
            throw new DomainException('Venda cancelada não pode gerar nota fiscal.');
        }
        if (! $config->prontaParaEmitir()) {
            throw new DomainException('Configure o CNPJ e o token da API fiscal antes de emitir.');
        }
        if ((float) $venda->desconto > 0) {
            // TODO: confirmar na documentação da Brasil NFe o campo de desconto por item.
            throw new DomainException('Emissão de venda com desconto ainda não suportada.');
        }

        $semFiscal = $venda->itens()->with('produto')->get()
            ->filter(fn (VendaItem $i) => blank($i->produto->ncm) || blank($i->produto->cfop))
            ->map(fn (VendaItem $i) => $i->produto->nome);

        if ($semFiscal->isNotEmpty()) {
            throw new DomainException('Produtos sem NCM ou CFOP: '.$semFiscal->implode(', ').'.');
        }
    }

    private function montarNota(Venda $venda): array
    {
        return [
            'modelo' => DocumentoFiscal::NFCE,
            'forma_pagamento' => $venda->forma_pagamento,
            'total' => (float) $venda->total,
            'itens' => $venda->itens()->with('produto')->get()->map(fn (VendaItem $i) => [
                'nome' => $i->produto->nome,
                'ncm' => $i->produto->ncm,
                'cfop' => $i->produto->cfop,
                'quantidade' => (float) $i->quantidade,
                'valor_unitario' => (float) $i->preco_unitario, // preço congelado na venda
                'valor_total' => (float) $i->subtotal,
            ])->all(),
        ];
    }
}