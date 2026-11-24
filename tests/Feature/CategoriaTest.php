<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Produto;
use App\Models\TipoServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Óleo Motul 10W40',
            'unidade' => 'UN',
            'preco_venda' => '65,00',
        ], $extra);
    }

    public function test_sistema_ja_vem_com_categorias_e_servicos_padrao(): void
    {
        $oleos = Categoria::produtoPorNome('Óleos e lubrificantes');
        $this->assertNotNull($oleos);
        $this->assertSame('OLE', $oleos->prefixo);
        $this->assertSame('27101932', $oleos->ncm);

        $this->assertTrue(Categoria::deServico()->where('nome', 'Freios')->exists());
        $this->assertSame('Revisão geral', TipoServico::where('codigo', 'SRV-001')->value('nome'));

        // Prefixos não se repetem entre categorias de produto.
        $prefixos = Categoria::deProduto()->pluck('prefixo');
        $this->assertSame($prefixos->count(), $prefixos->unique()->count());
    }

    public function test_proximo_codigo_continua_do_maior_numero_e_ignora_codigos_fora_do_padrao(): void
    {
        $this->assertSame('FRE-001', Categoria::proximoCodigo('FRE'));

        Produto::factory()->create(['codigo' => 'FRE-001']);
        Produto::factory()->create(['codigo' => 'FRE-007']);
        Produto::factory()->create(['codigo' => 'FRE-ABC']);
        Produto::factory()->create(['codigo' => 'FREIO-99']);

        $this->assertSame('FRE-008', Categoria::proximoCodigo('FRE'));
    }

    public function test_cadastro_sem_codigo_gera_pelo_prefixo_da_categoria(): void
    {
        $this->post('/produtos', $this->dados(['codigo' => '', 'categoria' => 'Óleos e lubrificantes']))
            ->assertRedirect('/produtos');
        $this->post('/produtos', $this->dados(['codigo' => '', 'nome' => 'Óleo Mobil 20W50', 'categoria' => 'óleos e lubrificantes']))
            ->assertRedirect('/produtos');

        $this->assertSame(['OLE-001', 'OLE-002'], Produto::orderBy('id')->pluck('codigo')->all());
        // O nome da categoria fica padronizado, mesmo digitado em minúsculas.
        $this->assertSame(['Óleos e lubrificantes'], Produto::distinct()->pluck('categoria')->all());
    }

    public function test_codigo_digitado_e_respeitado(): void
    {
        $this->post('/produtos', $this->dados(['codigo' => 'meu-123', 'categoria' => 'Freios']))->assertRedirect('/produtos');

        $this->assertSame('MEU-123', Produto::firstOrFail()->codigo);
    }

    public function test_sem_codigo_e_sem_categoria_padrao_exige_codigo(): void
    {
        $this->post('/produtos', $this->dados(['codigo' => '', 'categoria' => 'Categoria inventada']))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, Produto::count());
    }

    public function test_edicao_nao_troca_o_codigo_ao_mudar_a_categoria(): void
    {
        $produto = Produto::factory()->create(['codigo' => 'ANT-001']);

        $this->put("/produtos/{$produto->id}", $this->dados(['codigo' => 'ANT-001', 'categoria' => 'Freios']))
            ->assertSessionHasNoErrors();

        $this->assertSame('ANT-001', $produto->fresh()->codigo);
        $this->assertSame('Freios', $produto->fresh()->categoria);
    }

    public function test_telas_recebem_categorias_e_servicos_padrao(): void
    {
        $this->withoutVite();
        Produto::factory()->create(['codigo' => 'OLE-004']);

        $this->get('/produtos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Produtos/Index', false)
            ->where('categoriasPadrao', fn ($lista) => collect($lista)->firstWhere('prefixo', 'OLE')['proximo_codigo'] === 'OLE-005'));

        $this->get('/servicos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Servicos/Index', false)
            ->where('tiposServico.0.codigo', 'SRV-001'));
    }
}
