<?php

namespace Tests\Feature;

use App\Enums\TipoMovimentacao;
use App\Models\Movimentacao;
use App\Models\Produto;
use App\Models\Venda;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendaTest extends TestCase
{
    use RefreshDatabase;

    private Produto $oleo;
    private Produto $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        $estoque = app(EstoqueService::class);

        $this->oleo = Produto::factory()->create(['preco_venda' => 65, 'preco_custo' => 42]);
        $this->filtro = Produto::factory()->create(['preco_venda' => 28, 'preco_custo' => 14]);
        $estoque->entrar($this->oleo, 10);
        $estoque->entrar($this->filtro, 2);
    }

    private function vender(array $itens, array $extra = [])
    {
        return $this->from('/vendas')->post('/vendas', array_merge(['forma_pagamento' => 'pix', 'itens' => $itens], $extra));
    }

    public function test_venda_baixa_estoque_e_calcula_total_com_desconto(): void
    {
        $this->vender([
            ['produto_id' => $this->oleo->id, 'quantidade' => '2'],
            ['produto_id' => $this->filtro->id, 'quantidade' => '1'],
        ], ['desconto' => '8,00'])->assertSessionHas('success');

        $venda = Venda::firstOrFail();
        $this->assertEquals(158, (float) $venda->subtotal);   // 2×65 + 1×28
        $this->assertEquals(150, (float) $venda->total);
        $this->assertEquals(8, (float) $this->oleo->fresh()->saldo);
        $this->assertEquals(1, (float) $this->filtro->fresh()->saldo);

        $mov = Movimentacao::where('tipo', TipoMovimentacao::SaidaVenda->value)->firstOrFail();
        $this->assertSame('venda', $mov->origem_type);
        $this->assertSame("Venda #{$venda->id}", $mov->descricaoOrigem());
    }

    public function test_preco_vem_do_cadastro_e_fica_congelado_no_item(): void
    {
        // Tenta "forçar" um preço pelo navegador: deve ser ignorado.
        $this->vender([['produto_id' => $this->oleo->id, 'quantidade' => '1', 'preco_unitario' => '1']]);

        $this->oleo->update(['preco_venda' => 99]); // aumento de preço depois da venda

        $item = Venda::firstOrFail()->itens()->firstOrFail();
        $this->assertEquals(65, (float) $item->preco_unitario);
        $this->assertEquals(42, (float) $item->custo_unitario);
    }

    public function test_falta_de_saldo_em_um_item_cancela_a_venda_inteira(): void
    {
        $this->vender([
            ['produto_id' => $this->oleo->id, 'quantidade' => '3'],   // tem saldo
            ['produto_id' => $this->filtro->id, 'quantidade' => '5'], // NÃO tem (saldo 2)
        ])->assertSessionHasErrors('itens');

        $this->assertSame(0, Venda::count());
        $this->assertEquals(10, (float) $this->oleo->fresh()->saldo); // o 1º item não foi baixado
        $this->assertSame(0, Movimentacao::where('tipo', TipoMovimentacao::SaidaVenda->value)->count());
    }

    public function test_desconto_maior_que_subtotal_e_recusado(): void
    {
        $this->vender([['produto_id' => $this->filtro->id, 'quantidade' => '1']], ['desconto' => '50'])
            ->assertSessionHasErrors('desconto');

        $this->assertSame(0, Venda::count());
        $this->assertEquals(2, (float) $this->filtro->fresh()->saldo);
    }

    public function test_cancelamento_estorna_itens_e_preserva_a_venda(): void
    {
        $this->vender([['produto_id' => $this->oleo->id, 'quantidade' => '2']]);
        $venda = Venda::firstOrFail();

        $this->from('/vendas')->post("/vendas/{$venda->id}/cancelar", ['motivo' => 'Cliente desistiu'])
            ->assertSessionHas('success');

        $venda->refresh();
        $this->assertTrue($venda->estaCancelada());
        $this->assertEquals(10, (float) $this->oleo->fresh()->saldo);
        $this->assertSame(1, Movimentacao::where('tipo', TipoMovimentacao::Estorno->value)->count());

        // Segundo cancelamento é recusado e não estorna de novo.
        $this->from('/vendas')->post("/vendas/{$venda->id}/cancelar", ['motivo' => 'De novo'])
            ->assertSessionHasErrors('motivo');
        $this->assertEquals(10, (float) $this->oleo->fresh()->saldo);
    }

    public function test_cancelamento_exige_motivo(): void
    {
        $this->vender([['produto_id' => $this->oleo->id, 'quantidade' => '1']]);
        $venda = Venda::firstOrFail();

        $this->from('/vendas')->post("/vendas/{$venda->id}/cancelar", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertFalse($venda->fresh()->estaCancelada());
    }
}