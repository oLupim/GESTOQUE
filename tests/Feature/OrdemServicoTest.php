<?php

namespace Tests\Feature;

use App\Enums\TipoMovimentacao;
use App\Models\Cliente;
use App\Models\Motocicleta;
use App\Models\Movimentacao;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdemServicoTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;
    private Motocicleta $moto;
    private Produto $oleo;
    private Produto $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        $estoque = app(EstoqueService::class);

        $this->cliente = Cliente::create(['nome' => 'João']);
        $this->moto = Motocicleta::create(['cliente_id' => $this->cliente->id, 'placa' => 'ABC1D23', 'marca' => 'Honda', 'modelo' => 'CG 160', 'km_atual' => 30000]);
        $this->oleo = Produto::factory()->create(['preco_venda' => 65]);
        $this->filtro = Produto::factory()->create(['preco_venda' => 28]);
        $estoque->entrar($this->oleo, 10);
        $estoque->entrar($this->filtro, 2);
    }

    private function abrir(array $extra = [])
    {
        return $this->from('/servicos')->post('/servicos', array_merge([
            'cliente_id' => $this->cliente->id,
            'motocicleta_id' => $this->moto->id,
            'km_entrada' => '38.420',
            'problema' => 'Troca de óleo',
            'descricao_servico' => 'Troca de óleo e filtro',
            'valor_mao_obra' => '50,00',
            'mecanico' => 'Pedro',
            'pecas' => [
                ['produto_id' => $this->oleo->id, 'quantidade' => '2'],
                ['produto_id' => $this->filtro->id, 'quantidade' => '1'],
            ],
        ], $extra));
    }

    public function test_abrir_os_baixa_pecas_calcula_total_e_atualiza_km(): void
    {
        $this->abrir()->assertRedirect('/servicos/1');

        $os = OrdemServico::firstOrFail();
        $this->assertSame(OrdemServico::ABERTA, $os->status);
        $this->assertEquals(158, (float) $os->valor_pecas);  // 2×65 + 28
        $this->assertEquals(208, (float) $os->total);        // + 50 de mão de obra
        $this->assertEquals(8, (float) $this->oleo->fresh()->saldo);
        $this->assertSame(38420, $this->moto->fresh()->km_atual);

        $mov = Movimentacao::where('tipo', TipoMovimentacao::SaidaServico->value)->firstOrFail();
        $this->assertSame("Serviço #{$os->id}", $mov->descricaoOrigem());
    }

    public function test_moto_de_outro_cliente_e_recusada(): void
    {
        $outro = Cliente::create(['nome' => 'Maria']);

        $this->abrir(['cliente_id' => $outro->id])->assertSessionHasErrors('motocicleta_id');
        $this->assertSame(0, OrdemServico::count());
    }

    public function test_peca_sem_saldo_impede_abrir_e_nada_e_baixado(): void
    {
        $this->abrir(['pecas' => [
            ['produto_id' => $this->oleo->id, 'quantidade' => '2'],
            ['produto_id' => $this->filtro->id, 'quantidade' => '5'], // saldo 2
        ]])->assertSessionHasErrors('pecas');

        $this->assertSame(0, OrdemServico::count());
        $this->assertEquals(10, (float) $this->oleo->fresh()->saldo);
    }

    public function test_remover_peca_estorna_e_recalcula(): void
    {
        $this->abrir();
        $os = OrdemServico::firstOrFail();
        $itemOleo = $os->itens()->where('produto_id', $this->oleo->id)->firstOrFail();

        $this->from("/servicos/{$os->id}")->delete("/servicos/{$os->id}/pecas/{$itemOleo->id}")->assertSessionHas('success');

        $this->assertEquals(10, (float) $this->oleo->fresh()->saldo);
        $this->assertEquals(78, (float) $os->fresh()->total); // 28 + 50
        $this->assertSame(1, Movimentacao::where('tipo', TipoMovimentacao::Estorno->value)->count());
    }

    public function test_adicionar_peca_depois_baixa_e_soma(): void
    {
        $this->abrir(['pecas' => []]);
        $os = OrdemServico::firstOrFail();

        $this->from("/servicos/{$os->id}")->post("/servicos/{$os->id}/pecas", ['produto_id' => $this->filtro->id, 'quantidade' => '1'])
            ->assertSessionHas('success');
        $this->from("/servicos/{$os->id}")->post("/servicos/{$os->id}/pecas", ['produto_id' => $this->filtro->id, 'quantidade' => '9'])
            ->assertSessionHasErrors('quantidade');

        $this->assertEquals(1, (float) $this->filtro->fresh()->saldo);
        $this->assertEquals(78, (float) $os->fresh()->total);
    }

    public function test_cancelar_os_devolve_todas_as_pecas_e_bloqueia_alteracoes(): void
    {
        $this->abrir();
        $os = OrdemServico::firstOrFail();

        $this->from("/servicos/{$os->id}")->post("/servicos/{$os->id}/cancelar", ['motivo' => 'Cliente desistiu'])->assertSessionHas('success');

        $this->assertSame(OrdemServico::CANCELADA, $os->fresh()->status);
        $this->assertEquals(10, (float) $this->oleo->fresh()->saldo);
        $this->assertEquals(2, (float) $this->filtro->fresh()->saldo);

        $this->from("/servicos/{$os->id}")->post("/servicos/{$os->id}/pecas", ['produto_id' => $this->oleo->id, 'quantidade' => '1'])
            ->assertSessionHasErrors('os');
        $this->assertEquals(10, (float) $this->oleo->fresh()->saldo);
    }

    public function test_fluxo_de_status_e_finalizacao(): void
    {
        $this->abrir();
        $os = OrdemServico::firstOrFail();
        $url = "/servicos/{$os->id}/status";

        $this->from("/servicos/{$os->id}")->post($url, ['status' => 'em_andamento'])->assertSessionHasNoErrors();
        $this->from("/servicos/{$os->id}")->post($url, ['status' => 'aguardando_peca'])->assertSessionHasNoErrors();
        $this->from("/servicos/{$os->id}")->post($url, ['status' => 'finalizada'])->assertSessionHasNoErrors();

        $os->refresh();
        $this->assertSame(OrdemServico::FINALIZADA, $os->status);
        $this->assertNotNull($os->finalizada_em);

        // Finalizada não volta e não aceita mudanças.
        $this->from("/servicos/{$os->id}")->post($url, ['status' => 'em_andamento'])->assertSessionHasErrors('os');
        $this->from("/servicos/{$os->id}")->put("/servicos/{$os->id}", ['valor_mao_obra' => '999'])->assertSessionHasErrors('os');
        $this->assertEquals(208, (float) $os->fresh()->total);
    }
}