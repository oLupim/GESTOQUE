<?php

namespace App\Http\Controllers;

use App\Http\Requests\MotocicletaRequest;
use App\Models\Cliente;
use App\Models\Motocicleta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MotocicletaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Motocicletas/Index', [
            'motos' => Motocicleta::with('cliente:id,nome,telefone')->orderBy('modelo')->get()->map(fn (Motocicleta $m) => [
                'id' => $m->id,
                'placa' => $m->placa,
                'marca' => $m->marca,
                'modelo' => $m->modelo,
                'ano' => $m->ano,
                'cor' => $m->cor,
                'km_atual' => $m->km_atual,
                'cliente' => $m->cliente->nome,
                'cliente_id' => $m->cliente_id,
                'telefone' => $m->cliente->telefone,
            ]),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(MotocicletaRequest $request): RedirectResponse
    {
        $moto = Motocicleta::create($request->validated());

        return back()->with('success', "Moto {$moto->modelo} ({$moto->placa}) cadastrada.");
    }

    public function update(MotocicletaRequest $request, Motocicleta $motocicleta): RedirectResponse
    {
        $motocicleta->update($request->validated());

        return back()->with('success', 'Dados da moto atualizados.');
    }
}