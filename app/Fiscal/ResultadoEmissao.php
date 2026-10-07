<?php

namespace App\Fiscal;

/** Resposta do provedor, já traduzida para o vocabulário do Gestoque. */
final class ResultadoEmissao
{
    public function __construct(
        public readonly bool $autorizada,
        public readonly ?int $codigo,
        public readonly ?string $mensagem,
        public readonly ?string $chave = null,
        public readonly ?int $numero = null,
        public readonly ?int $serie = null,
        public readonly ?string $protocolo = null,
        public readonly ?string $xml = null,
        public readonly ?string $pdf = null,
        public readonly array $bruto = [],
    ) {}
}