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

class VendaController extends Controller
{
    public function store(StoreVendaRequest $request, ConfirmarVenda $confirmar): RedirectResponse
    {
        try {
            $venda = $confirmar->executar($request->validated(), $request->user());
        } catch (EstoqueInsuficienteException $e) {
            return back()->withErrors(['itens' => $e->getMessage()]);
        }

        return back()
            ->with('success', "Venda #{$venda->id} confirmada.")
            ->with('venda_id', $venda->id);
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