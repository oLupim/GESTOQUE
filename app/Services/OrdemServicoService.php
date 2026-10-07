<?php

namespace App\Services;

use App\Enums\TipoMovimentacao;
use App\Models\Motocicleta;
use App\Models\OrdemServico;
use App\Models\OsItem;
use App\Models\Produto;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdemServicoService
{
    public function __construct(private EstoqueService $estoque) {}

    /** Abre a OS. Se alguma peça inicial não tiver saldo, nada é gravado. */
    public function abrir(array $dados, ?User $usuario = null): OrdemServico
    {
        $moto = Motocicleta::findOrFail($dados['motocicleta_id']);

        if ((int) $moto->cliente_id !== (int) $dados['cliente_id']) {
            throw ValidationException::withMessages(['motocicleta_id' => 'Esta moto não pertence ao cliente escolhido.']);
        }

        return DB::transaction(function () use ($dados, $moto, $usuario) {
            $os = OrdemServico::create([
                'cliente_id' => $dados['cliente_id'],
                'motocicleta_id' => $moto->id,
                'status' => OrdemServico::ABERTA,
                'mecanico' => $dados['mecanico'] ?? null,
                'km_entrada' => $dados['km_entrada'] ?? null,
                'problema' => $dados['problema'] ?? null,
                'descricao_servico' => $dados['descricao_servico'] ?? null,
                'valor_mao_obra' => $dados['valor_mao_obra'] ?? 0,
                'user_id' => $usuario?->id ?? auth()->id(),
            ]);

            if (! empty($dados['km_entrada']) && (int) $dados['km_entrada'] > $moto->km_atual) {
                $moto->update(['km_atual' => (int) $dados['km_entrada']]);
            }

            foreach ($dados['pecas'] ?? [] as $peca) {
                $this->adicionarPeca($os, (int) $peca['produto_id'], $peca['quantidade'], $usuario);
            }

            $this->recalcular($os);

            return $os;
        });
    }

    /** Peça usada no serviço: baixa o estoque na hora (SAIDA_SERVICO com origem na OS). */
    public function adicionarPeca(OrdemServico $os, int $produtoId, float|string $quantidade, ?User $usuario = null): OsItem
    {
        $this->exigirEditavel($os);

        return DB::transaction(function () use ($os, $produtoId, $quantidade, $usuario) {
            $produto = Produto::findOrFail($produtoId);
            $qtd = round((float) $quantidade, 3);
            $preco = (float) $produto->preco_venda;

            $item = $os->itens()->create([
                'produto_id' => $produto->id,
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'custo_unitario' => (float) $produto->preco_custo,
                'subtotal' => round($preco * $qtd, 2),
            ]);

            $mov = $this->estoque->sair($produto, $qtd, TipoMovimentacao::SaidaServico, $os, $usuario);
            $item->update(['movimentacao_id' => $mov->id]);

            $this->recalcular($os);

            return $item;
        });
    }

    /** Peça retirada da OS: estorna a saída dela e devolve ao estoque. */
    public function removerPeca(OsItem $item, ?User $usuario = null): void
    {
        $os = $item->ordemServico;
        $this->exigirEditavel($os);

        DB::transaction(function () use ($item, $os, $usuario) {
            if ($item->movimentacao) {
                $this->estoque->estornar($item->movimentacao, "Peça removida da OS #{$os->id}", $usuario);
            }
            $item->delete();
            $this->recalcular($os);
        });
    }

    public function atualizar(OrdemServico $os, array $dados): void
    {
        $this->exigirEditavel($os);

        $os->update(array_intersect_key($dados, array_flip(['mecanico', 'problema', 'descricao_servico', 'valor_mao_obra'])));
        $this->recalcular($os);
    }

    public function alterarStatus(OrdemServico $os, string $novo): void
    {
        if (! in_array($novo, OrdemServico::TRANSICOES[$os->status] ?? [], true)) {
            throw new DomainException(sprintf(
                'Não é possível mudar de "%s" para "%s".',
                $os->rotulo(),
                OrdemServico::ROTULOS[$novo] ?? $novo,
            ));
        }

        $os->update([
            'status' => $novo,
            'finalizada_em' => $novo === OrdemServico::FINALIZADA ? now() : null,
        ]);
    }

    /** Cancela a OS e devolve todas as peças ao estoque. A OS fica no histórico. */
    public function cancelar(OrdemServico $os, string $motivo, ?User $usuario = null): void
    {
        $this->exigirEditavel($os);

        DB::transaction(function () use ($os, $motivo, $usuario) {
            $os->itens()->with('movimentacao')->get()->each(function (OsItem $item) use ($os, $usuario) {
                if ($item->movimentacao && ! $item->movimentacao->estornadaPor()->exists()) {
                    $this->estoque->estornar($item->movimentacao, "Cancelamento da OS #{$os->id}", $usuario);
                }
            });

            $os->update([
                'status' => OrdemServico::CANCELADA,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $motivo,
            ]);
        });
    }

    private function exigirEditavel(OrdemServico $os): void
    {
        if (! $os->editavel()) {
            throw new DomainException("A OS #{$os->id} está {$os->rotulo()} e não pode ser alterada.");
        }
    }

    private function recalcular(OrdemServico $os): void
    {
        $pecas = (float) $os->itens()->sum('subtotal');
        $os->update([
            'valor_pecas' => $pecas,
            'total' => round($pecas + (float) $os->valor_mao_obra, 2),
        ]);
    }
}