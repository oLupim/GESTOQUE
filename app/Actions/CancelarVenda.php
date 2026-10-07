<?php

namespace App\Actions;

use App\Enums\TipoMovimentacao;
use App\Models\Movimentacao;
use App\Models\User;
use App\Models\Venda;
use App\Services\EstoqueService;
use Illuminate\Support\Facades\DB;
use LogicException;

class CancelarVenda
{
    public function __construct(private EstoqueService $estoque) {}

    public function executar(Venda $venda, string $motivo, ?User $usuario = null): Venda
    {
        return DB::transaction(function () use ($venda, $motivo, $usuario) {
            $venda = Venda::whereKey($venda->id)->lockForUpdate()->firstOrFail();

            if ($venda->estaCancelada()) {
                throw new LogicException("A venda #{$venda->id} já está cancelada.");
            }

            // Estorna cada saída da venda. A venda e as saídas originais continuam no histórico.
            $venda->movimentacoes()
                ->where('tipo', TipoMovimentacao::SaidaVenda->value)
                ->get()
                ->each(fn (Movimentacao $m) => $this->estoque->estornar($m, "Cancelamento da venda #{$venda->id}: {$motivo}", $usuario));

            $venda->update([
                'status' => Venda::CANCELADA,
                'cancelada_em' => now(),
                'motivo_cancelamento' => $motivo,
            ]);

            return $venda;
        });
    }
}