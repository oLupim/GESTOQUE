<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contas de acesso. Só o administrador entra aqui.
 * Não existe exclusão: vendas e movimentações guardam quem fez, então a conta é desativada.
 */
class UsuarioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Usuarios/Index', [
            'usuarios' => User::orderByDesc('ativo')->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'perfil' => $u->perfil,
                'ativo' => (bool) $u->ativo,
                'criado_em' => $u->created_at?->format('d/m/Y'),
            ]),
            'perfis' => User::PERFIS,
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $dados['ativo'] = $request->boolean('ativo', true);

        // O cast 'hashed' do User criptografa a senha.
        $usuario = User::create($dados);

        return back()->with('success', "Conta de \"{$usuario->name}\" criada.");
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $dados = $request->validated();
        $dados['ativo'] = $request->boolean('ativo', true);

        if (blank($dados['password'] ?? null)) {
            unset($dados['password']); // senha vazia = manter a atual
        }

        $usuario->update($dados);

        return back()->with('success', 'Conta atualizada.');
    }
}
