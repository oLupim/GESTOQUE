<?php

namespace Tests\Feature;

use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_inicial_redireciona_para_produtos(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_listagem_de_produtos_carrega_com_dados_do_banco(): void
    {
        $this->withoutVite();
        Produto::factory()->count(3)->create();

        $this->get('/produtos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Produtos/Index', false)
                ->has('produtos', 3)
                ->has('categorias')
                ->has('marcas'));
    }
}