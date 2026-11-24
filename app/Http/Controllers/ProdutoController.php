<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Produto;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\TipoMovimentacao;
use App\Http\Requests\ProdutoRequest;
use App\Services\EstoqueService;
use App\Services\InterpretarEmbalagem;
use Illuminate\Http\JsonResponse;
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
            'codigo_barras' => $p->codigo_barras,
            'nome' => $p->nome,
            'categoria' => $p->categoria,
            'marca' => $p->marca,
            'unidade' => $p->unidade,
            'ncm' => $p->ncm,
            'cfop' => $p->cfop,
            'ativo' => (bool) $p->ativo,
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
            // Categorias padrão com prefixo, próximo código e sugestões (unidade, NCM, CFOP) para o cadastro rápido.
            'categoriasPadrao' => Categoria::deProduto()->orderBy('nome')->get()->map(fn (Categoria $c) => $c->paraFormulario()),
        ]);
    }
        public function store(ProdutoRequest $request, EstoqueService $estoque): RedirectResponse
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

    /**
     * Edita os dados do produto. O estoque (saldo) NÃO muda aqui: só por movimentação,
     * para manter a rastreabilidade. Mudar o preço não altera vendas antigas (preço congelado no item).
     * Chega por POST com _method=PUT porque pode trazer foto (o PHP não lê arquivo em PUT).
     */
    public function update(ProdutoRequest $request, Produto $produto): RedirectResponse
    {
        $dados = $request->validated();
        unset($dados['foto'], $dados['remover_foto']);
        $dados['preco_custo'] ??= 0;
        $dados['estoque_minimo'] ??= 0;
        $dados['ativo'] = $request->boolean('ativo', true);

        $disco = Storage::disk(config('gestoque.disco_imagens'));
        $fotoAntiga = $produto->foto_path;

        if ($request->hasFile('foto')) {
            $dados['foto_path'] = $request->file('foto')->store('produtos', config('gestoque.disco_imagens'));
        } elseif ($request->boolean('remover_foto')) {
            $dados['foto_path'] = null;
        }

        $produto->update($dados);

        // Só apaga a foto antiga depois que o banco foi atualizado.
        if (array_key_exists('foto_path', $dados) && $fotoAntiga) {
            $disco->delete($fotoAntiga);
        }

        return back()->with('success', "Produto \"{$produto->nome}\" atualizado.");
    }

    /** Recebe o texto lido na foto da embalagem (OCR no celular) e devolve sugestões de nome, marca e categoria. */
    public function interpretarEmbalagem(Request $request, InterpretarEmbalagem $interpretar): JsonResponse
    {
        $dados = $request->validate([
            'linhas' => ['required', 'array', 'max:80'],
            'linhas.*.texto' => ['required', 'string', 'max:200'],
            'linhas.*.altura' => ['nullable', 'numeric'],
            'linhas.*.confianca' => ['nullable', 'numeric'],
        ]);

        return response()->json($interpretar->interpretar($dados['linhas']));
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