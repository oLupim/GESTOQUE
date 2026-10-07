<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $logado = false; // cliente não tem conta

    public function test_cliente_ve_so_produtos_ativos_com_estoque(): void
    {
        $this->withoutVite();
        $estoque = app(EstoqueService::class);

        // Mesma categoria e nomes sem acento: a ordem do catálogo fica previsível.
        $disponivel = Produto::factory()->create(['nome' => 'Filtro', 'categoria' => 'Peças', 'estoque_minimo' => 2]);
        $estoque->entrar($disponivel, 10);

        $poucas = Produto::factory()->create(['nome' => 'Vela', 'categoria' => 'Peças', 'estoque_minimo' => 5]);
        $estoque->entrar($poucas, 3);

        Produto::factory()->create(['nome' => 'Zerado', 'categoria' => 'Peças']);                    // sem estoque
        $inativo = Produto::factory()->create(['nome' => 'Inativo', 'categoria' => 'Peças', 'ativo' => false]);
        $estoque->entrar($inativo, 5);

        $this->get('/catalogo')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Catalogo', false)
            ->has('produtos', 2)
            ->where('produtos.0.nome', 'Filtro')
            ->where('produtos.0.ultimas', false)
            ->where('produtos.1.nome', 'Vela')
            ->where('produtos.1.ultimas', true));
    }

    public function test_catalogo_nao_expoe_custo_nem_quantidade(): void
    {
        $this->withoutVite();
        $p = Produto::factory()->create(['preco_custo' => 42.17]);
        app(EstoqueService::class)->entrar($p, 7);

        $this->get('/catalogo')->assertInertia(fn (Assert $page) => $page
            ->has('produtos.0', fn (Assert $item) => $item
                ->hasAll(['id', 'nome', 'marca', 'categoria', 'preco', 'ultimas'])
                ->missingAll(['preco_custo', 'saldo', 'estoque_minimo', 'codigo'])));
    }
}