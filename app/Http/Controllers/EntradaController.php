<?php

namespace App\Http\Controllers;

use App\Actions\RegistrarEntrada;
use App\Http\Requests\StoreEntradaRequest;
use App\Models\Entrada;
use App\Models\EntradaItem;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EntradaController extends Controller
{
    public function index(): Response
    {
        $entradas = Entrada::with(['itens.produto:id,nome', 'usuario:id,name'])
            ->latest('data_entrada')->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Entrada $e) => [
                'id' => $e->id,
                'data' => $e->data_entrada->format('d/m/Y'),
                'fornecedor' => $e->fornecedor,
                'documento' => $e->documento,
                'itens' => $e->itens->count(),
                'resumo' => $e->itens->map(fn (EntradaItem $i) => $i->produto->nome.' ×'.(float) $i->quantidade)->implode(', '),
                'total' => round($e->itens->sum(fn (EntradaItem $i) => (float) $i->quantidade * (float) $i->custo_unitario), 2),
                'usuario' => $e->usuario?->name ?? '—',
            ]);

        return Inertia::render('Entradas/Index', [
            'entradas' => $entradas,
            'produtos' => Produto::where('ativo', true)->orderBy('nome')->get()->map(fn (Produto $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'codigo' => $p->codigo,
                'unidade' => $p->unidade,
                'saldo' => (float) $p->saldo,
                'preco_custo' => (float) $p->preco_custo,
            ]),
        ]);
    }

    public function store(StoreEntradaRequest $request, RegistrarEntrada $registrar): RedirectResponse
    {
        $entrada = $registrar->executar($request->validated(), $request->user());

        return redirect()->route('entradas.index')
            ->with('success', "Entrada #{$entrada->id} registrada com {$entrada->itens()->count()} item(ns).");
    }
}