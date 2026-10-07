<?php

namespace Tests\Feature;

use App\Enums\TipoMovimentacao;
use App\Exceptions\EstoqueInsuficienteException;
use App\Models\Movimentacao;
use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class EstoqueTest extends TestCase
{
    use RefreshDatabase;

    private EstoqueService $estoque;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estoque = app(EstoqueService::class);
    }

    public function test_entrada_aumenta_saldo_e_registra_movimentacao(): void
    {
        $produto = Produto::factory()->create();

        $mov = $this->estoque->entrar($produto, 15);

        $this->assertEquals(15, (float) $produto->fresh()->saldo);
        $this->assertSame(TipoMovimentacao::Entrada, $mov->tipo);
        $this->assertEquals(0, (float) $mov->saldo_anterior);
        $this->assertEquals(15, (float) $mov->saldo_posterior);
    }

    public function test_saida_acima_do_saldo_e_bloqueada_e_nada_e_gravado(): void
    {
        $produto = Produto::factory()->create();
        $this->estoque->entrar($produto, 2);

        try {
            $this->estoque->sair($produto, 3, TipoMovimentacao::SaidaVenda);
            $this->fail('Deveria ter bloqueado a saída.');
        } catch (EstoqueInsuficienteException $e) {
            $this->assertEquals(2, $e->disponivel);
        }

        $this->assertEquals(2, (float) $produto->fresh()->saldo);
        $this->assertSame(1, Movimentacao::count());
    }

    public function test_estorno_devolve_saldo_preserva_original_e_nao_repete(): void
    {
        $produto = Produto::factory()->create();
        $this->estoque->entrar($produto, 20);
        $saida = $this->estoque->sair($produto, 2, TipoMovimentacao::SaidaVenda);

        $this->estoque->estornar($saida, 'Venda cancelada');

        $this->assertEquals(20, (float) $produto->fresh()->saldo);
        $this->assertSame(3, Movimentacao::count());

        $this->expectException(LogicException::class);
        $this->estoque->estornar($saida, 'De novo');
    }

    public function test_perda_exige_motivo(): void
    {
        $produto = Produto::factory()->create();
        $this->estoque->entrar($produto, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->estoque->sair($produto, 1, TipoMovimentacao::Perda);
    }

    public function test_movimentacao_nao_pode_ser_alterada(): void
    {
        $produto = Produto::factory()->create();
        $mov = $this->estoque->entrar($produto, 5);

        $this->expectException(LogicException::class);
        $mov->update(['quantidade' => 50]);
    }

    public function test_soma_das_movimentacoes_e_igual_ao_saldo(): void
    {
        $produto = Produto::factory()->create();

        $this->estoque->entrar($produto, 10, TipoMovimentacao::EstoqueInicial);
        $this->estoque->entrar($produto, 20);
        $venda = $this->estoque->sair($produto, 2, TipoMovimentacao::SaidaVenda);
        $this->estoque->sair($produto, 1, TipoMovimentacao::SaidaServico);
        $this->estoque->sair($produto, 1, TipoMovimentacao::AjusteNegativo, motivo: 'Inventário');
        $this->estoque->estornar($venda, 'Cancelamento');

        $soma = (float) Movimentacao::where('produto_id', $produto->id)->sum('quantidade');

        $this->assertEquals(28, (float) $produto->fresh()->saldo);
        $this->assertEquals(28, $soma);
    }

    public function test_quantidade_fracionada(): void
    {
        $produto = Produto::factory()->create(['unidade' => 'L']);
        $this->estoque->entrar($produto, 5);
        $this->estoque->sair($produto, '0.9', TipoMovimentacao::SaidaServico);

        $this->assertEquals(4.1, (float) $produto->fresh()->saldo);
    }
}