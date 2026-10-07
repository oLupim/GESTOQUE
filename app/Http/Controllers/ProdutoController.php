<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\TipoMovimentacao;
use App\Http\Requests\StoreProdutoRequest;
use App\Services\EstoqueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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
        public function store(StoreProdutoRequest $request, EstoqueService $estoque): RedirectResponse
    {
        $dados = $request->validated();
        $inicial = (float) ($dados['estoque_inicial'] ?? 0);
        unset($dados['estoque_inicial']);
        $dados['preco_custo'] ??= 0;
        $dados['estoque_minimo'] ??= 0;

        // Produto e estoque inicial na mesma transação: ou grava os dois, ou nenhum.
        $produto = DB::transaction(function () use ($dados, $inicial, $estoque) {
            $produto = Produto::create($dados);

            if ($inicial > 0) {
                $estoque->entrar($produto, $inicial, TipoMovimentacao::EstoqueInicial, motivo: 'Cadastro do produto');
            }

            return $produto;
        });

        return redirect()->route('produtos.index')
            ->with('success', "Produto \"{$produto->nome}\" cadastrado.");
    }
}