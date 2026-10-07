<?php

namespace App\Http\Controllers;

use App\Enums\TipoMovimentacao;
use App\Exceptions\EstoqueInsuficienteException;
use App\Models\Movimentacao;
use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EstoqueController extends Controller
{
    public function index(): Response
    {
        $produtos = Produto::orderBy('nome')->get();

        // Data da última movimentação de cada produto, numa consulta só.
        $ultimas = Movimentacao::selectRaw('produto_id, MAX(created_at) AS ultima')
            ->groupBy('produto_id')
            ->pluck('ultima', 'produto_id');

        return Inertia::render('Estoque/Index', [
            'produtos' => $produtos->map(fn (Produto $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nome' => $p->nome,
                'categoria' => $p->categoria,
                'unidade' => $p->unidade,
                'saldo' => (float) $p->saldo,
                'estoque_minimo' => (float) $p->estoque_minimo,
                'situacao' => $p->situacaoEstoque(),
                'ultima_movimentacao' => isset($ultimas[$p->id])
                    ? Carbon::parse($ultimas[$p->id])->format('d/m/Y H:i')
                    : null,
            ]),

            'movimentacoes' => Movimentacao::with(['produto:id,nome,unidade', 'usuario:id,name'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Movimentacao $m) => [
                    'id' => $m->id,
                    'data' => $m->created_at->format('d/m/Y H:i'),
                    'produto' => $m->produto->nome,
                    'tipo' => $m->tipo->value,
                    'tipo_label' => $m->tipo->label(),
                    'quantidade' => (float) $m->quantidade,
                    'saldo_anterior' => (float) $m->saldo_anterior,
                    'saldo_posterior' => (float) $m->saldo_posterior,
                    'origem' => $m->descricaoOrigem(),
                    'motivo' => $m->motivo,
                    'usuario' => $m->usuario?->name ?? '—',
                ]),

            'resumo' => [
                'total' => $produtos->count(),
                'valor' => round($produtos->sum(fn (Produto $p) => (float) $p->saldo * (float) $p->preco_custo), 2),
                'baixo' => $produtos->filter(fn (Produto $p) => in_array($p->situacaoEstoque(), ['Baixo', 'Crítico']))->count(),
                'sem_estoque' => $produtos->filter(fn (Produto $p) => (float) $p->saldo <= 0)->count(),
            ],

            'tipos' => collect(TipoMovimentacao::manuais())->map(fn (TipoMovimentacao $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'entrada' => $t->ehEntrada(),
                'exige_motivo' => $t->exigeMotivo(),
            ]),
        ]);
    }

    public function store(Request $request, EstoqueService $estoque): RedirectResponse
    {
        $request->merge(['quantidade' => str_replace(',', '.', (string) $request->input('quantidade'))]);

        $dados = $request->validate([
            'produto_id' => ['required', 'exists:produtos,id'],
            'tipo' => ['required', Rule::in(array_map(fn (TipoMovimentacao $t) => $t->value, TipoMovimentacao::manuais()))],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [
            'required' => 'Informe :attribute.',
            'exists' => 'Produto não encontrado.',
            'in' => 'Tipo de movimentação inválido.',
            'numeric' => ':Attribute deve ser um número.',
            'gt' => ':Attribute deve ser maior que zero.',
        ], [
            'produto_id' => 'o produto',
            'tipo' => 'o tipo',
            'quantidade' => 'a quantidade',
            'motivo' => 'o motivo',
        ]);

        $tipo = TipoMovimentacao::from($dados['tipo']);

        if ($tipo->exigeMotivo() && blank($dados['motivo'] ?? null)) {
            return back()->withErrors(['motivo' => 'Informe o motivo para '.mb_strtolower($tipo->label()).'.']);
        }

        $produto = Produto::findOrFail($dados['produto_id']);

        try {
            $tipo->ehEntrada()
                ? $estoque->entrar($produto, $dados['quantidade'], $tipo, motivo: $dados['motivo'] ?? null)
                : $estoque->sair($produto, $dados['quantidade'], $tipo, motivo: $dados['motivo'] ?? null);
        } catch (EstoqueInsuficienteException $e) {
            return back()->withErrors(['quantidade' => $e->getMessage()]);
        }

        return back()->with('success', "Movimentação registrada: {$tipo->label()} — {$produto->nome}.");
    }
}