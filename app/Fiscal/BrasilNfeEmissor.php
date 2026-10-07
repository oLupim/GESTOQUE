<?php

namespace App\Fiscal;

use App\Models\ConfiguracaoFiscal;
use Illuminate\Support\Facades\Http;

/**
 * Adaptador da API Brasil NFe.
 * Campos conforme o exemplo público em brasilnfe.com.br/products/nfc-e.
 */
class BrasilNfeEmissor implements EmissorFiscal
{
    public const URL = 'https://api.brasilnfe.com.br/services/Fiscal/EnviarNotaFiscal';

    // Códigos de forma de pagamento da SEFAZ (tPag).
    private const PAGAMENTO = ['dinheiro' => 1, 'credito' => 3, 'debito' => 4, 'pix' => 17];

    public function emitir(array $nota, ConfiguracaoFiscal $config): ResultadoEmissao
    {
        $resposta = Http::withHeaders(['Token' => $config->api_token])
            ->acceptJson()
            ->timeout(40)
            ->post(self::URL, $this->montar($nota, $config));

        $json = $resposta->json() ?? [];
        $retorno = $json['ReturnNF'] ?? [];

        return new ResultadoEmissao(
            autorizada: (bool) ($retorno['Ok'] ?? false),
            codigo: isset($retorno['CodStatusRespostaSefaz']) ? (int) $retorno['CodStatusRespostaSefaz'] : null,
            mensagem: $retorno['DsStatusRespostaSefaz'] ?? $json['Message'] ?? "Resposta HTTP {$resposta->status()} sem detalhes.",
            chave: $retorno['ChaveNF'] ?? null,
            numero: isset($retorno['Numero']) ? (int) $retorno['Numero'] : null,
            serie: isset($retorno['Serie']) ? (int) $retorno['Serie'] : null,
            protocolo: $retorno['Protocolo'] ?? null, // TODO: confirmar o nome na documentação completa
            xml: isset($json['Base64Xml']) ? base64_decode($json['Base64Xml']) : null,
            pdf: isset($json['Base64File']) ? base64_decode($json['Base64File']) : null,
            bruto: $retorno,
        );
    }

    /** Traduz a nota do Gestoque para o JSON da Brasil NFe. */
    private function montar(array $nota, ConfiguracaoFiscal $config): array
    {
        return [
            'TipoAmbiente' => $config->ambiente,
            'ModeloDocumento' => $nota['modelo'],
            'NaturezaOperacao' => 'Venda de mercadoria',
            'Finalidade' => 1,         // normal
            'ConsumidorFinal' => true,
            'IndicadorPresenca' => 1,  // venda presencial
            'Produtos' => array_map(fn (array $i) => [
                'NmProduto' => $i['nome'],
                'NCM' => $i['ncm'],
                'CFOP' => (int) $i['cfop'],
                'Quantidade' => $i['quantidade'],
                'ValorUnitario' => $i['valor_unitario'],
                'ValorTotal' => $i['valor_total'],
            ], $nota['itens']),
            'Pagamentos' => [[
                'TipoPagamento' => self::PAGAMENTO[$nota['forma_pagamento']] ?? 99,
                'Valor' => $nota['total'],
            ]],
        ];
    }
}