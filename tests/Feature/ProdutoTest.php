<?php

namespace Tests\Feature;

use App\Enums\TipoMovimentacao;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdutoTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'codigo' => 'ole-001',
            'nome' => 'Óleo Motul 10W40',
            'unidade' => 'UN',
            'preco_custo' => '42,00',
            'preco_venda' => '65,00',
            'estoque_inicial' => '12',
            'estoque_minimo' => '5',
            'ncm' => '2710.19.32',
            'cfop' => '5102',
        ], $extra);
    }

    public function test_cadastra_produto_com_estoque_inicial(): void
    {
        $this->post('/produtos', $this->dados())
            ->assertRedirect('/produtos')
            ->assertSessionHas('success');

        $produto = Produto::firstOrFail();
        $this->assertSame('OLE-001', $produto->codigo);
        $this->assertSame('27101932', $produto->ncm);
        $this->assertEquals(65, (float) $produto->preco_venda);
        $this->assertEquals(12, (float) $produto->saldo);
        $this->assertSame(TipoMovimentacao::EstoqueInicial, $produto->movimentacoes()->first()->tipo);
    }

    public function test_sem_estoque_inicial_nao_cria_movimentacao(): void
    {
        $this->post('/produtos', $this->dados(['estoque_inicial' => '']))->assertRedirect('/produtos');

        $this->assertSame(0, Produto::firstOrFail()->movimentacoes()->count());
    }

    public function test_valida_campos_obrigatorios_e_formatos(): void
    {
        $this->post('/produtos', $this->dados(['nome' => '', 'preco_venda' => '0', 'ncm' => '123']))
            ->assertSessionHasErrors(['nome', 'preco_venda', 'ncm']);

        $this->assertSame(0, Produto::count());
    }

    public function test_nao_permite_codigo_repetido(): void
    {
        $this->post('/produtos', $this->dados());
        $this->post('/produtos', $this->dados(['nome' => 'Outro']))->assertSessionHasErrors('codigo');

        $this->assertSame(1, Produto::count());
    }


    public function test_custo_e_minimo_vazios_assumem_zero(): void
    {
        $this->post('/produtos', $this->dados(['preco_custo' => '', 'estoque_minimo' => '']))
            ->assertRedirect('/produtos');

        $produto = Produto::firstOrFail();
        $this->assertEquals(0, (float) $produto->preco_custo);
        $this->assertEquals(0, (float) $produto->estoque_minimo);
    }
}