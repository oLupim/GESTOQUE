<?php

namespace App\Exceptions;

use App\Models\Produto;
use DomainException;

class EstoqueInsuficienteException extends DomainException
{
    public function __construct(
        public readonly Produto $produto,
        public readonly float $solicitado,
        public readonly float $disponivel,
    ) {
        parent::__construct(sprintf(
            'Estoque insuficiente para "%s": solicitado %s, disponível %s.',
            $produto->nome,
            self::fmt($solicitado),
            self::fmt($disponivel),
        ));
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    }
}