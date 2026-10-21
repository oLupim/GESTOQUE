<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private function venda(string $status, float $total, $quando = null): Venda
    {
        return Venda::forceCreate([
            'status' => $status, 'forma_pagamento' => 'pix', 'subtotal' => $total,
            'desconto' => 0, 'total' => $total, 'user_id' => auth()->id(),
            'created_at' => $quando ?? now(),
        ]);
    }

    public function test_soma_so_vendas_confirmadas_do_periodo_e_calcula_lucro(): void
    {
        $produto = Produto::factory()->create(['nome' => 'Óleo Motul']);

        $v = $this->venda(Venda::CONFIRMADA, 100);
        VendaItem::forceCreate([
            'venda_id' => $v->id, 'produto_id' => $produto->id, 'quantidade' => 2,
            'preco_unitario' => 50, 'custo_unitario' => 30, 'subtotal' => 100,
        ]);
        $this->venda(Venda::CANCELADA, 999);                        // cancelada: fora
        $this->venda(Venda::CONFIRMADA, 500, now()->subMonths(2));  // fora do período

        $hoje = now()->toDateString();

        $this->withoutVite()->get("/relatorios?de={$hoje}&ate={$hoje}")
            ->assertInertia(fn (Assert $p) => $p
                ->component('Relatorios/Index', false)
                ->where('resumo.vendas', 1)
                ->where('resumo.faturamento', fn ($v) => (float) $v === 100.0)
                ->where('resumo.lucro', fn ($v) => (float) $v === 40.0)
                ->where('maisVendidos.0.nome', 'Óleo Motul')
                ->where('maisVendidos.0.quantidade', fn ($v) => (float) $v === 2.0)
                ->has('vendasPorDia', 1));
    }

    public function test_data_final_antes_da_inicial_e_recusada(): void
    {
        $this->get('/relatorios?de=2026-10-10&ate=2026-10-01')->assertSessionHasErrors('ate');
    }

    public function test_tela_abre_sem_dados(): void
    {
        $this->withoutVite()->get('/relatorios')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('resumo.vendas', 0));
    }
}