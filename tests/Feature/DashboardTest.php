<?php

namespace Tests\Feature;

use App\Actions\CancelarVenda;
use App\Actions\ConfirmarVenda;
use App\Models\Cliente;
use App\Models\Motocicleta;
use App\Models\Produto;
use App\Services\EstoqueService;
use App\Services\OrdemServicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicadores_refletem_o_banco(): void
    {
        $this->withoutVite();
        $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(10, 0)); // meio do mês, de manhã

        $p = Produto::factory()->create(['preco_venda' => 50, 'estoque_minimo' => 25]);
        app(EstoqueService::class)->entrar($p, 30);
        $vender = fn (int $q) => app(ConfirmarVenda::class)->executar(['forma_pagamento' => 'pix', 'itens' => [['produto_id' => $p->id, 'quantidade' => $q]]]);

        $this->travel(-1)->days();
        $vender(2);                                     // ontem: R$ 100
        $this->travelBack();
        $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(10, 0));

        $vender(3);                                     // hoje: R$ 150
        $cancelada = $vender(1);                        // hoje, mas cancelada: não conta
        app(CancelarVenda::class)->executar($cancelada, 'teste');

        $c = Cliente::create(['nome' => 'A']);
        $m = Motocicleta::create(['cliente_id' => $c->id, 'placa' => 'ABC1234', 'marca' => 'H', 'modelo' => 'X']);
        app(OrdemServicoService::class)->abrir(['cliente_id' => $c->id, 'motocicleta_id' => $m->id, 'valor_mao_obra' => 80]);

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard', false)
            ->where('saudacao', 'Bom dia')
            ->where('cards.vendas_hoje', 150)
            ->where('cards.qtd_hoje', 1)
            ->where('cards.variacao_hoje', 50)          // 150 vs 100 = +50%
            ->where('cards.vendas_mes', 250)
            ->where('cards.servicos_abertos', 1)
            ->where('cards.estoque_baixo', 1)           // saldo 25 ≤ mínimo... ver abaixo
            ->has('grafico', 7)
            ->where('grafico.6.valor', 150)
            ->where('grafico.5.valor', 100));
    }
}