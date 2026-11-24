<?php

namespace Tests\Feature;

use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** O leitor de código de barras (no celular) depende destes comportamentos do servidor. */
class CodigoBarrasTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'codigo' => 'OLE-001',
            'nome' => 'Óleo Motul 10W40',
            'unidade' => 'UN',
            'preco_venda' => '65,00',
        ], $extra);
    }

    public function test_codigo_lido_e_gravado_so_com_numeros(): void
    {
        $this->post('/produtos', $this->dados(['codigo_barras' => '789-1234 567890']))->assertRedirect('/produtos');

        $this->assertSame('7891234567890', Produto::firstOrFail()->codigo_barras);
    }

    public function test_nao_permite_dois_produtos_com_o_mesmo_codigo_de_barras(): void
    {
        Produto::factory()->create(['codigo_barras' => '7891234567890']);

        $this->post('/produtos', $this->dados(['codigo_barras' => '7891234567890']))
            ->assertSessionHasErrors('codigo_barras');

        $this->assertSame(1, Produto::count());
    }

    public function test_codigo_de_etiqueta_curto_e_com_letras_e_aceito(): void
    {
        // Etiqueta de peça (Code 39/128) pode ser curta e ter letras; fica só com letras e números, em maiúsculas.
        $this->post('/produtos', $this->dados(['codigo_barras' => 'kr-s901']))->assertSessionHasNoErrors();
        $this->assertSame('KRS901', Produto::firstOrFail()->codigo_barras);

        $this->post('/produtos', $this->dados(['codigo' => 'OLE-002', 'codigo_barras' => '12']))
            ->assertSessionHasErrors('codigo_barras'); // curto demais (menos de 4)
    }

    public function test_telas_de_produto_e_venda_recebem_o_codigo_de_barras(): void
    {
        $this->withoutVite();
        $produto = Produto::factory()->create(['codigo_barras' => '7891234567890']);
        app(\App\Services\EstoqueService::class)->entrar($produto, 3);

        $this->get('/produtos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Produtos/Index', false)
            ->where('produtos.0.codigo_barras', '7891234567890'));

        $this->get('/vendas/nova')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Vendas/Nova', false)
            ->where('produtos.0.codigo_barras', '7891234567890'));
    }
}
