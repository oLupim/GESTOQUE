<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VendaTelaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_nova_venda_carrega_produtos_e_formas(): void
    {
        $this->withoutVite();
        Produto::factory()->count(2)->create();

        $this->get('/vendas/nova')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Vendas/Nova', false)
            ->has('produtos', 2)
            ->has('formas', 4));
    }

    public function test_venda_confirmada_volta_para_nova_venda_com_resumo(): void
    {
        $p = Produto::factory()->create(['preco_venda' => 30]);
        app(EstoqueService::class)->entrar($p, 5);

        $this->post('/vendas', [
            'forma_pagamento' => 'dinheiro',
            'itens' => [['produto_id' => $p->id, 'quantidade' => 2]],
        ])
            ->assertRedirect('/vendas/nova')
            ->assertSessionHas('venda', fn ($v) => $v['total'] === 60.0 && $v['forma'] === 'Dinheiro');
    }

    public function test_lista_de_vendas_mostra_resumo_do_dia(): void
    {
        $this->withoutVite();
        $p = Produto::factory()->create(['preco_venda' => 30]);
        app(EstoqueService::class)->entrar($p, 5);
        $this->post('/vendas', ['forma_pagamento' => 'pix', 'itens' => [['produto_id' => $p->id, 'quantidade' => 1]]]);

        $this->get('/vendas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Vendas/Index', false)
            ->has('vendas', 1)
            ->where('resumo.quantidade', 1)
            ->where('resumo.faturamento', 30));
    }
}