<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EstoqueTelaTest extends TestCase
{
    use RefreshDatabase;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->produto = Produto::factory()->create();
        app(EstoqueService::class)->entrar($this->produto, 5);
    }

    public function test_tela_de_estoque_carrega_produtos_e_historico(): void
    {
        $this->withoutVite();

        $this->get('/estoque')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Estoque/Index', false)
            ->has('produtos', 1)
            ->has('movimentacoes', 1)
            ->where('resumo.total', 1));
    }

    public function test_entrada_manual_aumenta_saldo(): void
    {
        $this->from('/estoque')->post('/estoque/movimentacoes', [
            'produto_id' => $this->produto->id, 'tipo' => 'ENTRADA', 'quantidade' => '3',
        ])->assertRedirect('/estoque')->assertSessionHas('success');

        $this->assertEquals(8, (float) $this->produto->fresh()->saldo);
    }

    public function test_perda_sem_motivo_e_recusada(): void
    {
        $this->from('/estoque')->post('/estoque/movimentacoes', [
            'produto_id' => $this->produto->id, 'tipo' => 'PERDA', 'quantidade' => '1',
        ])->assertSessionHasErrors('motivo');

        $this->assertEquals(5, (float) $this->produto->fresh()->saldo);
    }

    public function test_saida_acima_do_saldo_mostra_erro_na_quantidade(): void
    {
        $this->from('/estoque')->post('/estoque/movimentacoes', [
            'produto_id' => $this->produto->id, 'tipo' => 'AJUSTE_NEGATIVO', 'quantidade' => '9', 'motivo' => 'Inventário',
        ])->assertSessionHasErrors('quantidade');

        $this->assertEquals(5, (float) $this->produto->fresh()->saldo);
    }

    public function test_tipos_automaticos_nao_podem_ser_lancados_pela_tela(): void
    {
        $this->from('/estoque')->post('/estoque/movimentacoes', [
            'produto_id' => $this->produto->id, 'tipo' => 'SAIDA_VENDA', 'quantidade' => '1',
        ])->assertSessionHasErrors('tipo');
    }
}