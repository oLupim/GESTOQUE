<?php

namespace Tests\Feature;

use App\Enums\TipoMovimentacao;
use App\Models\Entrada;
use App\Models\Movimentacao;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntradaTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrada_com_varios_itens_atualiza_estoque_e_rastreia_origem(): void
    {
        $filtro = Produto::factory()->create(['preco_custo' => 10]);
        $vela = Produto::factory()->create(['preco_custo' => 8]);

        $this->post('/entradas', [
            'fornecedor' => 'Distribuidora X',
            'documento' => '12345',
            'data_entrada' => now()->toDateString(),
            'itens' => [
                ['produto_id' => $filtro->id, 'quantidade' => '10', 'custo_unitario' => '14,50'],
                ['produto_id' => $vela->id, 'quantidade' => '12', 'custo_unitario' => ''],
            ],
        ])->assertRedirect('/entradas')->assertSessionHas('success');

        $entrada = Entrada::firstOrFail();
        $this->assertSame(2, $entrada->itens()->count());

        $this->assertEquals(10, (float) $filtro->fresh()->saldo);
        $this->assertEquals(14.5, (float) $filtro->fresh()->preco_custo); // custo atualizado
        $this->assertEquals(12, (float) $vela->fresh()->saldo);
        $this->assertEquals(8, (float) $vela->fresh()->preco_custo);      // custo vazio mantém o anterior

        $mov = Movimentacao::where('produto_id', $filtro->id)->firstOrFail();
        $this->assertSame(TipoMovimentacao::Entrada, $mov->tipo);
        $this->assertSame('entrada', $mov->origem_type);
        $this->assertTrue($mov->origem->is($entrada));
        $this->assertSame("Entrada #{$entrada->id}", $mov->descricaoOrigem());
    }

    public function test_entrada_sem_itens_e_recusada(): void
    {
        $this->post('/entradas', ['data_entrada' => now()->toDateString(), 'itens' => []])
            ->assertSessionHasErrors('itens');

        $this->assertSame(0, Entrada::count());
    }

    public function test_quantidade_zero_e_produto_repetido_sao_recusados(): void
    {
        $p = Produto::factory()->create();

        $this->post('/entradas', [
            'data_entrada' => now()->toDateString(),
            'itens' => [
                ['produto_id' => $p->id, 'quantidade' => '0'],
                ['produto_id' => $p->id, 'quantidade' => '2'],
            ],
        ])->assertSessionHasErrors(['itens.0.quantidade', 'itens.0.produto_id']);

        $this->assertSame(0, Entrada::count());
        $this->assertEquals(0, (float) $p->fresh()->saldo);
    }

    public function test_data_futura_e_recusada(): void
    {
        $p = Produto::factory()->create();

        $this->post('/entradas', [
            'data_entrada' => now()->addDay()->toDateString(),
            'itens' => [['produto_id' => $p->id, 'quantidade' => '1']],
        ])->assertSessionHasErrors('data_entrada');
    }
}