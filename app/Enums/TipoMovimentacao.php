<?php

namespace App\Enums;

enum TipoMovimentacao: string
{
    case EstoqueInicial = 'ESTOQUE_INICIAL';
    case Entrada = 'ENTRADA';
    case Devolucao = 'DEVOLUCAO';
    case AjustePositivo = 'AJUSTE_POSITIVO';

    case SaidaVenda = 'SAIDA_VENDA';
    case SaidaServico = 'SAIDA_SERVICO';
    case Perda = 'PERDA';
    case AjusteNegativo = 'AJUSTE_NEGATIVO';

    case Estorno = 'ESTORNO';

    public function label(): string
    {
        return match ($this) {
            self::EstoqueInicial => 'Estoque inicial',
            self::Entrada => 'Entrada por compra',
            self::Devolucao => 'Devolução',
            self::AjustePositivo => 'Ajuste positivo',
            self::SaidaVenda => 'Saída por venda',
            self::SaidaServico => 'Saída por serviço',
            self::Perda => 'Perda / avaria',
            self::AjusteNegativo => 'Ajuste negativo',
            self::Estorno => 'Estorno',
        };
    }

    public function ehEntrada(): bool
    {
        return in_array($this, [self::EstoqueInicial, self::Entrada, self::Devolucao, self::AjustePositivo], true);
    }

    public function ehSaida(): bool
    {
        return in_array($this, [self::SaidaVenda, self::SaidaServico, self::Perda, self::AjusteNegativo], true);
    }

    /** Lançamentos que precisam de justificativa. */
    public function exigeMotivo(): bool
    {
        return in_array($this, [self::Perda, self::AjustePositivo, self::AjusteNegativo, self::Estorno], true);
    }

    /** Tipos que o usuário pode lançar pela tela "Nova movimentação". */
    public static function manuais(): array
    {
        return [self::Entrada, self::Devolucao, self::Perda, self::AjustePositivo, self::AjusteNegativo];
    }
}