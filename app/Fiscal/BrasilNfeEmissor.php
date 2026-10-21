<?php

namespace App\Fiscal;

use App\Models\ConfiguracaoFiscal;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Adaptador da API Brasil NFe.
 * Campos conforme brasilnfe.com.br/api/nf-e-e-nfc-e (Transmitir NFe / NFCe).
 */
class BrasilNfeEmissor implements EmissorFiscal
{
    public const URL = 'https://api.brasilnfe.com.br/services/fiscal/EnviarNotaFiscal';

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
            codigo: ! empty($retorno['CodStatusRespostaSefaz']) ? (int) $retorno['CodStatusRespostaSefaz'] : null,
            mensagem: $this->mensagem($json, $retorno, $resposta->status()),
            chave: $retorno['ChaveNF'] ?? null,
            numero: ! empty($retorno['Numero']) ? (int) $retorno['Numero'] : null,
            serie: ! empty($retorno['Serie']) ? (int) $retorno['Serie'] : null,
            protocolo: $retorno['NumeroProtocolo'] ?? null,
            xml: ! empty($json['Base64Xml']) ? base64_decode($json['Base64Xml']) : null,
            pdf: ! empty($json['Base64File']) ? base64_decode($json['Base64File']) : null,
            // Guarda a resposta inteira (sem os arquivos, que já vão para o disco).
            bruto: Arr::except($json, ['Base64Xml', 'Base64File']),
        );
    }

    /** Erro da Brasil NFe > motivo da SEFAZ > status HTTP. Avisos vão junto. */
    private function mensagem(array $json, array $retorno, int $http): string
    {
        $texto = filled($json['Error'] ?? null) ? $json['Error']
            : (filled($retorno['DsStatusRespostaSefaz'] ?? null) ? $retorno['DsStatusRespostaSefaz']
            : "Resposta HTTP {$http} sem detalhes.");

        $avisos = array_filter((array) ($json['Avisos'] ?? []));

        return $avisos ? $texto.' | Avisos: '.implode('; ', $avisos) : $texto;
    }

    /** Traduz a nota do Gestoque para o JSON da Brasil NFe. */
    private function montar(array $nota, ConfiguracaoFiscal $config): array
    {
        $payload = [
            'TipoAmbiente' => (int) $config->ambiente,
            'ModeloDocumento' => (int) $nota['modelo'],
            'NaturezaOperacao' => 'Venda de mercadoria',
            'Finalidade' => 1,         // normal
            'ConsumidorFinal' => true,
            'IndicadorPresenca' => 1,  // venda presencial
            'ValorDesconto' => (float) ($nota['desconto'] ?? 0), // a API rateia entre os itens
            'Produtos' => array_map(fn (array $i) => [
                'CodProdutoServico' => (string) ($i['codigo'] ?? $i['produto_id'] ?? ''),
                'NmProduto' => $i['nome'],
                'NCM' => $i['ncm'],
                'CFOP' => (int) $i['cfop'],
                'UnidadeComercial' => $i['unidade'] ?? 'UN',
                'Quantidade' => (float) $i['quantidade'],
                'ValorUnitario' => (float) $i['valor_unitario'],
                'ValorTotal' => (float) $i['valor_total'],
                'OrigemProduto' => (int) ($i['origem'] ?? 0), // 0 = nacional
                'Imposto' => $this->imposto($config),
            ], $nota['itens']),
            'Pagamentos' => [[
                'FormaPagamento' => self::PAGAMENTO[$nota['forma_pagamento']] ?? 99,
                'VlPago' => (float) $nota['total'],
            ]],
        ];

        // NF-e (55) exige destinatário. Na NFC-e (65) é opcional.
        if (! empty($nota['cliente'])) {
            $payload['Cliente'] = [
                'CpfCnpj' => $nota['cliente']['cpf_cnpj'],
                'NmCliente' => $nota['cliente']['nome'],
            ];
        }

        return $payload;
    }

    /**
     * Simples Nacional / MEI (CRT 1 ou 4): ICMS CSOSN 102 (sem crédito),
     * PIS/COFINS 49 (outras saídas) sem destaque.
     * Confirmar com o contador antes de emitir em produção.
     */
    private function imposto(ConfiguracaoFiscal $config): array
    {
        return [
            'ICMS' => ['CodSituacaoTributaria' => '102'],
            'PIS' => ['CodSituacaoTributaria' => '49', 'Aliquota' => 0, 'BaseCalculo' => 0],
            'COFINS' => ['CodSituacaoTributaria' => '49', 'Aliquota' => 0, 'BaseCalculo' => 0],
        ];
    }
}