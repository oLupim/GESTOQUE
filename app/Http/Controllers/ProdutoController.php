<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Inertia\Inertia;
use Inertia\Response;

class ProdutoController extends Controller
{
    public function index(): Response
    {
        $produtos = Produto::orderBy('nome')->get()->map(fn (Produto $p) => [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nome' => $p->nome,
            'categoria' => $p->categoria,
            'marca' => $p->marca,
            'unidade' => $p->unidade,
            'preco_custo' => (float) $p->preco_custo,
            'preco_venda' => (float) $p->preco_venda,
            'saldo' => (float) $p->saldo,
            'estoque_minimo' => (float) $p->estoque_minimo,
            'situacao' => $p->situacaoEstoque(),
        ]);

        return Inertia::render('Produtos/Index', [
            'produtos' => $produtos,
            'categorias' => Produto::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
            'marcas' => Produto::whereNotNull('marca')->distinct()->orderBy('marca')->pluck('marca'),
        ]);
    }
}