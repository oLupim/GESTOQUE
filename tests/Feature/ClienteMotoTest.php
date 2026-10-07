<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Motocicleta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClienteMotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_cliente_limpando_cpf_e_telefone(): void
    {
        $this->from('/clientes')->post('/clientes', [
            'nome' => 'João da Silva', 'cpf_cnpj' => '123.456.789-00', 'telefone' => '(54) 99876-5432',
        ])->assertSessionHas('success');

        $c = Cliente::firstOrFail();
        $this->assertSame('12345678900', $c->cpf_cnpj);
        $this->assertSame('54998765432', $c->telefone);
    }

    public function test_cpf_repetido_ou_com_tamanho_errado_e_recusado(): void
    {
        Cliente::create(['nome' => 'A', 'cpf_cnpj' => '12345678900']);

        $this->from('/clientes')->post('/clientes', ['nome' => 'B', 'cpf_cnpj' => '123.456.789-00'])->assertSessionHasErrors('cpf_cnpj');
        $this->from('/clientes')->post('/clientes', ['nome' => 'C', 'cpf_cnpj' => '1234'])->assertSessionHasErrors('cpf_cnpj');
        $this->assertSame(1, Cliente::count());
    }

    public function test_edicao_mantem_o_proprio_cpf(): void
    {
        $c = Cliente::create(['nome' => 'A', 'cpf_cnpj' => '12345678900']);

        $this->from('/clientes')->put("/clientes/{$c->id}", ['nome' => 'A Editado', 'cpf_cnpj' => '12345678900'])
            ->assertSessionHasNoErrors();
        $this->assertSame('A Editado', $c->fresh()->nome);
    }

    public function test_placa_antiga_e_mercosul_sao_normalizadas(): void
    {
        $c = Cliente::create(['nome' => 'A']);

        $this->from('/motocicletas')->post('/motocicletas', ['cliente_id' => $c->id, 'placa' => 'abc-1234', 'marca' => 'Honda', 'modelo' => 'CG 160']);
        $this->from('/motocicletas')->post('/motocicletas', ['cliente_id' => $c->id, 'placa' => 'def2e34', 'marca' => 'Yamaha', 'modelo' => 'Fazer', 'km_atual' => '45.800']);

        $this->assertEqualsCanonicalizing(['ABC1234', 'DEF2E34'], Motocicleta::pluck('placa')->all());
        $this->assertSame(45800, Motocicleta::where('placa', 'DEF2E34')->first()->km_atual);
    }

    public function test_placa_invalida_ou_repetida_e_recusada(): void
    {
        $c = Cliente::create(['nome' => 'A']);
        Motocicleta::create(['cliente_id' => $c->id, 'placa' => 'ABC1234', 'marca' => 'Honda', 'modelo' => 'CG']);

        $this->from('/motocicletas')->post('/motocicletas', ['cliente_id' => $c->id, 'placa' => 'ABC-1234', 'marca' => 'H', 'modelo' => 'X'])
            ->assertSessionHasErrors('placa');
        $this->from('/motocicletas')->post('/motocicletas', ['cliente_id' => $c->id, 'placa' => '12ABCDE', 'marca' => 'H', 'modelo' => 'X'])
            ->assertSessionHasErrors('placa');
        $this->assertSame(1, Motocicleta::count());
    }

    public function test_telas_carregam(): void
    {
        $this->withoutVite();
        $c = Cliente::create(['nome' => 'A']);
        Motocicleta::create(['cliente_id' => $c->id, 'placa' => 'ABC1234', 'marca' => 'Honda', 'modelo' => 'CG']);

        $this->get('/clientes')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Clientes/Index', false)->has('clientes.0.motos', 1));
        $this->get('/motocicletas')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Motocicletas/Index', false)->where('motos.0.cliente', 'A'));
    }
}