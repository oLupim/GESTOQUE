<?php

namespace App\Actions;

use App\Enums\TipoMovimentacao;
use App\Models\Entrada;
use App\Models\Produto;
use App\Models\User;
use App\Services\EstoqueService;
use Illuminate\Support\Facades\DB;

class RegistrarEntrada
{
    public function __construct(private EstoqueService $estoque) {}

    /**
     * @param  array{fornecedor?: ?string, documento?: ?string, data_entrada: string, observacao?: ?string,
     *               itens: array<int, array{produto_id: int, quantidade: float|string, custo_unitario?: float|string|null}>}  $dados
     */
    public function executar(array $dados, ?User $usuario = null): Entrada
    {
        // Entrada, itens, movimentações e custos: tudo ou nada.
        return DB::transaction(function () use ($dados, $usuario) {
            $entrada = Entrada::create([
                'fornecedor' => $dados['fornecedor'] ?? null,
                'documento' => $dados['documento'] ?? null,
                'data_entrada' => $dados['data_entrada'],
                'observacao' => $dados['observacao'] ?? null,
                'user_id' => $usuario?->id ?? auth()->id(),
            ]);

            foreach ($dados['itens'] as $item) {
                $produto = Produto::findOrFail($item['produto_id']);
                $custo = isset($item['custo_unitario']) && $item['custo_unitario'] !== '' ? (float) $item['custo_unitario'] : null;

                $entrada->itens()->create([
                    'produto_id' => $produto->id,
                    'quantidade' => $item['quantidade'],
                    'custo_unitario' => $custo ?? (float) $produto->preco_custo,
                ]);

                $this->estoque->entrar($produto, $item['quantidade'], TipoMovimentacao::Entrada, $entrada, $usuario);

                // O cadastro passa a refletir o último custo de compra.
                if ($custo !== null && $custo > 0) {
                    $produto->update(['preco_custo' => $custo]);
                }
            }

            return $entrada;
        });
    }
}