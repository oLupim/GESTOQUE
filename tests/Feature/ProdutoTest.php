<?php

namespace Tests\Feature;

use App\Enums\TipoMovimentacao;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_cadastra_produto_com_foto(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));

        $this->post('/produtos', $this->dados(['foto' => UploadedFile::fake()->image('oleo.jpg', 800, 600)]))
            ->assertRedirect('/produtos')
            ->assertSessionHasNoErrors();

        $produto = Produto::firstOrFail();
        $this->assertStringStartsWith('produtos/', $produto->foto_path);
        Storage::disk(config('gestoque.disco_imagens'))->assertExists($produto->foto_path);
        $this->assertNotNull($produto->fotoUrl());
    }

    public function test_foto_que_nao_e_imagem_e_recusada_e_nada_e_gravado(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));

        $this->post('/produtos', $this->dados(['foto' => UploadedFile::fake()->create('nota.pdf', 50, 'application/pdf')]))
            ->assertSessionHasErrors(['foto' => 'Envie uma imagem (JPG, PNG ou WEBP).']);

        $this->assertSame(0, Produto::count());
    }

    public function test_trocar_foto_apaga_a_antiga(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));
        $disco = Storage::disk(config('gestoque.disco_imagens'));
        $produto = Produto::factory()->create();

        $this->post("/produtos/{$produto->id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasNoErrors();
        $antiga = $produto->fresh()->foto_path;

        $this->post("/produtos/{$produto->id}/foto", ['foto' => UploadedFile::fake()->image('b.jpg')])->assertSessionHasNoErrors();
        $nova = $produto->fresh()->foto_path;

        $this->assertNotSame($antiga, $nova);
        $disco->assertMissing($antiga);
        $disco->assertExists($nova);
    }

    public function test_remover_foto_do_produto(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));
        $produto = Produto::factory()->create();
        $this->post("/produtos/{$produto->id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg')]);
        $caminho = $produto->fresh()->foto_path;

        $this->post("/produtos/{$produto->id}/foto", ['remover_foto' => true])->assertSessionHasNoErrors();

        $this->assertNull($produto->fresh()->foto_path);
        Storage::disk(config('gestoque.disco_imagens'))->assertMissing($caminho);
    }

    /** A edição chega por POST com _method=put (igual ao formulário, que pode trazer foto). */
    private function editar(Produto $produto, array $dados)
    {
        return $this->post("/produtos/{$produto->id}", ['_method' => 'put', ...$this->dados(['estoque_inicial' => null]), ...$dados]);
    }

    public function test_edita_nome_e_preco(): void
    {
        $this->post('/produtos', $this->dados());
        $produto = Produto::firstOrFail();

        $this->editar($produto, ['nome' => 'Óleo Motul 20W50', 'preco_venda' => '70,00'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $produto->refresh();
        $this->assertSame('Óleo Motul 20W50', $produto->nome);
        $this->assertEquals(70, (float) $produto->preco_venda);
        $this->assertSame('OLE-001', $produto->codigo); // manter o próprio código não é "repetido"
    }

    public function test_edicao_nao_muda_o_estoque(): void
    {
        $this->post('/produtos', $this->dados(['estoque_inicial' => '12']));
        $produto = Produto::firstOrFail();

        // Mesmo mandando saldo/estoque_inicial, a edição ignora: estoque só muda por movimentação.
        $this->editar($produto, ['saldo' => 999, 'estoque_inicial' => '50'])->assertSessionHasNoErrors();

        $this->assertEquals(12, (float) $produto->fresh()->saldo);
        $this->assertSame(1, $produto->movimentacoes()->count());
    }

    public function test_edicao_nao_aceita_codigo_de_outro_produto(): void
    {
        $this->post('/produtos', $this->dados());
        $this->post('/produtos', $this->dados(['codigo' => 'COR-001', 'nome' => 'Correia']));
        $correia = Produto::where('codigo', 'COR-001')->firstOrFail();

        $this->editar($correia, ['codigo' => 'ole-001'])->assertSessionHasErrors('codigo');
    }

    public function test_produto_desativado_some_do_catalogo(): void
    {
        $this->post('/produtos', $this->dados());
        $produto = Produto::firstOrFail();

        $this->editar($produto, ['ativo' => false])->assertSessionHasNoErrors();

        $this->assertFalse($produto->fresh()->ativo);
        $this->withoutVite()->get('/catalogo')->assertInertia(fn ($page) => $page->has('produtos', 0));
    }

    public function test_edicao_troca_a_foto_e_apaga_a_antiga(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));
        $disco = Storage::disk(config('gestoque.disco_imagens'));
        $this->post('/produtos', $this->dados(['foto' => UploadedFile::fake()->image('a.jpg')]));
        $produto = Produto::firstOrFail();
        $antiga = $produto->foto_path;

        $this->editar($produto, ['foto' => UploadedFile::fake()->image('b.jpg')])->assertSessionHasNoErrors();

        $nova = $produto->fresh()->foto_path;
        $this->assertNotSame($antiga, $nova);
        $disco->assertMissing($antiga);
        $disco->assertExists($nova);
    }

    public function test_salvar_foto_sem_escolher_nada_avisa(): void
    {
        $produto = Produto::factory()->create();

        $this->post("/produtos/{$produto->id}/foto", [])->assertSessionHasErrors('foto');
    }
}