<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\TipoMovimentacao;
use App\Http\Requests\StoreProdutoRequest;
use App\Services\EstoqueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            'foto_url' => $p->fotoUrl(),
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
        unset($dados['estoque_inicial'], $dados['foto']);
        $dados['preco_custo'] ??= 0;
        $dados['estoque_minimo'] ??= 0;

        // A foto vai para o disco antes; se o banco falhar, o arquivo é apagado.
        $dados['foto_path'] = $request->file('foto')?->store('produtos', config('gestoque.disco_imagens'));

        try {
            // Produto e estoque inicial na mesma transação: ou grava os dois, ou nenhum.
            $produto = DB::transaction(function () use ($dados, $inicial, $estoque) {
                $produto = Produto::create($dados);

                if ($inicial > 0) {
                    $estoque->entrar($produto, $inicial, TipoMovimentacao::EstoqueInicial, motivo: 'Cadastro do produto');
                }

                return $produto;
            });
        } catch (\Throwable $e) {
            if ($dados['foto_path']) {
                Storage::disk(config('gestoque.disco_imagens'))->delete($dados['foto_path']);
            }
            throw $e;
        }

        return redirect()->route('produtos.index')
            ->with('success', "Produto \"{$produto->nome}\" cadastrado.");
    }

    /** Coloca, troca ou remove a foto de um produto já cadastrado. */
    public function foto(Request $request, Produto $produto): RedirectResponse
    {
        $request->validate([
            'foto' => Produto::REGRAS_FOTO,
            'remover_foto' => ['boolean'],
        ], Produto::MENSAGENS_FOTO);

        if (! $request->hasFile('foto') && ! $request->boolean('remover_foto')) {
            return back()->withErrors(['foto' => 'Tire ou escolha uma foto.']);
        }

        $disco = Storage::disk(config('gestoque.disco_imagens'));

        // Apaga a foto antiga para não acumular arquivos.
        if ($produto->foto_path) {
            $disco->delete($produto->foto_path);
        }

        $produto->update([
            'foto_path' => $request->file('foto')?->store('produtos', config('gestoque.disco_imagens')),
        ]);

        return back()->with('success', $produto->foto_path ? 'Foto do produto atualizada.' : 'Foto do produto removida.');
    }
}