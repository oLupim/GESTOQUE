<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function index(): Response
    {
        $produtos = Produto::where('ativo', true)
            ->where('saldo', '>', 0)
            ->orderBy('categoria')->orderBy('nome')
            ->get();

        return Inertia::render('Catalogo', [
            // Só campos públicos: nada de custo, margem ou quantidade exata.
            'produtos' => $produtos->map(fn (Produto $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'marca' => $p->marca,
                'categoria' => $p->categoria,
                'preco' => (float) $p->preco_venda,
                'ultimas' => (float) $p->saldo <= (float) $p->estoque_minimo,
            ])->values(),
            'categorias' => $produtos->pluck('categoria')->filter()->unique()->sort()->values(),
            'oficina' => [
                'nome' => config('gestoque.oficina'),
                'whatsapp' => config('gestoque.whatsapp'),
                'endereco' => config('gestoque.endereco'),
            ],
        ]);
    }
}