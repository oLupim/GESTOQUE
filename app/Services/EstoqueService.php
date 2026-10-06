<?php

namespace App\Services;

use App\Enums\TipoMovimentacao;
use App\Exceptions\EstoqueInsuficienteException;
use App\Models\Movimentacao;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Único ponto do sistema que altera o saldo de um produto.
 * Toda alteração gera uma Movimentacao na mesma transação.
 */
class EstoqueService
{
    public function entrar(
        Produto $produto,
        float|string $quantidade,
        TipoMovimentacao $tipo = TipoMovimentacao::Entrada,
        ?Model $origem = null,
        ?User $usuario = null,
        ?string $motivo = null,
    ): Movimentacao {
        if (! $tipo->ehEntrada()) {
            throw new InvalidArgumentException("{$tipo->value} não é um tipo de entrada.");
        }

        return $this->aplicar($produto, $tipo, $this->positiva($quantidade), $origem, $usuario, $motivo);
    }

    public function sair(
        Produto $produto,
        float|string $quantidade,
        TipoMovimentacao $tipo,
        ?Model $origem = null,
        ?User $usuario = null,
        ?string $motivo = null,
    ): Movimentacao {
        if (! $tipo->ehSaida()) {
            throw new InvalidArgumentException("{$tipo->value} não é um tipo de saída.");
        }

        return $this->aplicar($produto, $tipo, -$this->positiva($quantidade), $origem, $usuario, $motivo);
    }

    /** Gera o movimento inverso. A movimentação original continua no histórico. */
    public function estornar(Movimentacao $original, string $motivo, ?User $usuario = null): Movimentacao
    {
        if ($original->tipo === TipoMovimentacao::Estorno) {
            throw new LogicException('Não é possível estornar um estorno.');
        }

        return DB::transaction(function () use ($original, $motivo, $usuario) {
            $original = Movimentacao::whereKey($original->id)->lockForUpdate()->firstOrFail();

            if ($original->estornadaPor()->exists()) {
                throw new LogicException("A movimentação #{$original->id} já foi estornada.");
            }

            return $this->aplicar(
                $original->produto,
                TipoMovimentacao::Estorno,
                -(float) $original->quantidade,
                $original->origem_type ? $original->origem : null,
                $usuario,
                $motivo,
                estornoDe: $original,
            );
        });
    }

    private function aplicar(
        Produto $produto,
        TipoMovimentacao $tipo,
        float $delta,
        ?Model $origem,
        ?User $usuario,
        ?string $motivo,
        ?Movimentacao $estornoDe = null,
    ): Movimentacao {
        if ($tipo->exigeMotivo() && blank($motivo)) {
            throw new InvalidArgumentException("Informe o motivo para {$tipo->label()}.");
        }

        return DB::transaction(function () use ($produto, $tipo, $delta, $origem, $usuario, $motivo, $estornoDe) {
            // SELECT ... FOR UPDATE: trava o produto até o fim da transação.
            $travado = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();

            $anterior = (float) $travado->saldo;
            $posterior = round($anterior + $delta, 3);

            if ($posterior < 0) {
                throw new EstoqueInsuficienteException($travado, abs($delta), $anterior);
            }

            $mov = Movimentacao::create([
                'produto_id' => $travado->id,
                'tipo' => $tipo,
                'quantidade' => $delta,
                'saldo_anterior' => $anterior,
                'saldo_posterior' => $posterior,
                'origem_type' => $origem?->getMorphClass(),
                'origem_id' => $origem?->getKey(),
                'estorno_de_id' => $estornoDe?->id,
                'motivo' => $motivo,
                'user_id' => $usuario?->id ?? auth()->id(),
            ]);

            $travado->forceFill(['saldo' => $posterior])->save();
            $produto->setAttribute('saldo', $travado->saldo)->syncOriginalAttribute('saldo');

            return $mov;
        });
    }

    private function positiva(float|string $quantidade): float
    {
        $q = round((float) $quantidade, 3);

        if ($q <= 0) {
            throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
        }

        return $q;
    }
}