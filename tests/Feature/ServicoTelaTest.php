<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Motocicleta;
use App\Models\Produto;
use App\Services\EstoqueService;
use App\Services\OrdemServicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicoTelaTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_e_detalhe_da_os_carregam(): void
    {
        $this->withoutVite();
        $c = Cliente::create(['nome' => 'João']);
        $m = Motocicleta::create(['cliente_id' => $c->id, 'placa' => 'ABC1D23', 'marca' => 'Honda', 'modelo' => 'CG']);
        $p = Produto::factory()->create(['preco_venda' => 40]);
        app(EstoqueService::class)->entrar($p, 5);

        $os = app(OrdemServicoService::class)->abrir([
            'cliente_id' => $c->id, 'motocicleta_id' => $m->id, 'valor_mao_obra' => 60,
            'pecas' => [['produto_id' => $p->id, 'quantidade' => 1]],
        ]);

        $this->get('/servicos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Servicos/Index', false)
            ->has('ordens', 1)
            ->where('contagem.aberta', 1)
            ->has('clientes.0.motos', 1));

        $this->get("/servicos/{$os->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Servicos/Show', false)
            ->where('os.total', 100)
            ->has('os.itens', 1)
            ->has('os.transicoes', 3));
    }
}