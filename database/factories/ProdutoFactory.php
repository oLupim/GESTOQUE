<?php

namespace Database\Factories;

use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Produto> */
class ProdutoFactory extends Factory
{
    protected $model = Produto::class;

    public function definition(): array
    {
        $custo = fake()->randomFloat(2, 5, 200);

        return [
            'codigo' => strtoupper(fake()->unique()->bothify('???-###')),
            'nome' => fake()->words(3, true),
            'categoria' => fake()->randomElement(['Lubrificantes', 'Freios', 'Filtros', 'Transmissão', 'Ignição']),
            'unidade' => 'UN',
            'preco_custo' => $custo,
            'preco_venda' => round($custo * 1.7, 2),
            'estoque_minimo' => 5,
            'ncm' => '87149990',
            'cfop' => '5102',
        ];
    }
}