<?php

namespace App\Http\Controllers;

use App\Actions\CancelarVenda;
use App\Actions\ConfirmarVenda;
use App\Exceptions\EstoqueInsuficienteException;
use App\Http\Requests\StoreVendaRequest;
use App\Models\Venda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;
use App\Models\Produto;
use App\Models\VendaItem;
use Inertia\Inertia;
use Inertia\Response;

class VendaController extends Controller
{

    public function index(): Response
    {
        $hoje = Venda::where('status', Venda::CONFIRMADA)->whereDate('created_at', today());

        return Inertia::render('Vendas/Index', [
            'vendas' => Venda::with(['itens.produto:id,nome', 'usuario:id,name'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Venda $v) => [
                    'id' => $v->id,
                    'data' => $v->created_at->format('d/m/Y H:i'),
                    'itens' => $v->itens->count(),
                    'resumo' => $v->itens->map(fn (VendaItem $i) => $i->produto->nome.' ×'.(float) $i->quantidade)->implode(', '),
                    'forma' => Venda::FORMAS_PAGAMENTO[$v->forma_pagamento] ?? $v->forma_pagamento,
                    'total' => (float) $v->total,
                    'status' => $v->estaCancelada() ? 'Cancelada' : 'Concluída',
                    'motivo_cancelamento' => $v->motivo_cancelamento,
                    'usuario' => $v->usuario?->name ?? '—',
                ]),
            'resumo' => [
                'quantidade' => (clone $hoje)->count(),
                'faturamento' => (float) (clone $hoje)->sum('total'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Vendas/Nova', [
            'produtos' => Produto::where('ativo', true)->orderBy('nome')->get()->map(fn (Produto $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'codigo' => $p->codigo,
                'codigo_barras' => $p->codigo_barras,
                'unidade' => $p->unidade,
                'preco_venda' => (float) $p->preco_venda,
                'saldo' => (float) $p->saldo,
                'estoque_minimo' => (float) $p->estoque_minimo,
            ]),
            'formas' => Venda::FORMAS_PAGAMENTO,
        ]);
    }
    public function store(StoreVendaRequest $request, ConfirmarVenda $confirmar): RedirectResponse
    {
        try {
            $venda = $confirmar->executar($request->validated(), $request->user());
        } catch (EstoqueInsuficienteException $e) {
            return back()->withErrors(['itens' => $e->getMessage()]);
        }

        return redirect()->route('vendas.create')
            ->with('success', "Venda #{$venda->id} confirmada.")
            ->with('venda', [
                'id' => $venda->id,
                'total' => (float) $venda->total,
                'forma' => Venda::FORMAS_PAGAMENTO[$venda->forma_pagamento],
            ]);
    }

    public function cancelar(Request $request, Venda $venda, CancelarVenda $cancelar): RedirectResponse
    {
        $dados = $request->validate(
            ['motivo' => ['required', 'string', 'max:500']],
            ['motivo.required' => 'Informe o motivo do cancelamento.'],
        );

        try {
            $cancelar->executar($venda, $dados['motivo'], $request->user());
        } catch (LogicException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return back()->with('success', "Venda #{$venda->id} cancelada. Itens devolvidos ao estoque.");
    }
}