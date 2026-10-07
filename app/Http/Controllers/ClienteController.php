<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Models\Motocicleta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Clientes/Index', [
            'clientes' => Cliente::with('motocicletas')->orderBy('nome')->get()->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'cpf_cnpj' => $c->cpf_cnpj,
                'telefone' => $c->telefone,
                'email' => $c->email,
                'endereco' => $c->endereco,
                'observacoes' => $c->observacoes,
                'motos' => $c->motocicletas->map(fn (Motocicleta $m) => [
                    'id' => $m->id, 'placa' => $m->placa, 'marca' => $m->marca, 'modelo' => $m->modelo,
                    'ano' => $m->ano, 'cor' => $m->cor, 'km_atual' => $m->km_atual,
                ]),
            ]),
        ]);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $cliente = Cliente::create($request->validated());

        return back()->with('success', "Cliente \"{$cliente->nome}\" cadastrado.");
    }

    public function update(ClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return back()->with('success', 'Dados do cliente atualizados.');
    }
}