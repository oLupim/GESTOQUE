<?php

namespace App\Actions;

use App\Enums\TipoMovimentacao;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use App\Services\EstoqueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarVenda
{
    public function __construct(private EstoqueService $estoque) {}

    /**
     * @param  array{forma_pagamento: string, desconto?: float|string|null,
     *               itens: array<int, array{produto_id: int, quantidade: float|string}>}  $dados
     *
     * @throws \App\Exceptions\EstoqueInsuficienteException se algum item não tiver saldo (nada é gravado)
     */
    public function executar(array $dados, ?User $usuario = null): Venda
    {
        return DB::transaction(function () use ($dados, $usuario) {
            $venda = Venda::create([
                'status' => Venda::CONFIRMADA,
                'forma_pagamento' => $dados['forma_pagamento'],
                'subtotal' => 0,
                'desconto' => 0,
                'total' => 0,
                'user_id' => $usuario?->id ?? auth()->id(),
            ]);

            $subtotal = 0.0;

            foreach ($dados['itens'] as $item) {
                $produto = Produto::findOrFail($item['produto_id']);
                $qtd = round((float) $item['quantidade'], 3);

                // Preço SEMPRE do cadastro: o navegador não decide o valor da venda.
                $preco = (float) $produto->preco_venda;
                $linha = round($preco * $qtd, 2);

                $venda->itens()->create([
                    'produto_id' => $produto->id,
                    'quantidade' => $qtd,
                    'preco_unitario' => $preco,
                    'custo_unitario' => (float) $produto->preco_custo,
                    'subtotal' => $linha,
                ]);

                // Se faltar saldo, a exceção desfaz a transação inteira (inclusive os itens anteriores).
                $this->estoque->sair($produto, $qtd, TipoMovimentacao::SaidaVenda, $venda, $usuario);

                $subtotal += $linha;
            }

            $desconto = round((float) ($dados['desconto'] ?? 0), 2);

            if ($desconto > $subtotal) {
                throw ValidationException::withMessages(['desconto' => 'O desconto não pode ser maior que o subtotal.']);
            }

            $venda->update([
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'total' => round($subtotal - $desconto, 2),
            ]);

            return $venda;
        });
    }
}