<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracaoFiscal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ConfiguracaoController extends Controller
{
    public function edit(Request $request)
    {
        $fiscal = ConfiguracaoFiscal::atual();

        return Inertia::render('Configuracoes/Index', [
            'perfil' => $request->user()->only('name', 'email'),
            'fiscal' => [
                ...$fiscal->only('razao_social', 'cnpj', 'inscricao_estadual', 'crt', 'ambiente'),
                // O token nunca vai para a tela, só a informação de que existe.
                'tem_token' => filled($fiscal->api_token),
            ],
        ]);
    }

    public function perfil(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
        ], [], ['name' => 'nome', 'email' => 'e-mail']);

        $request->user()->update($dados);

        return back()->with('success', 'Dados da conta atualizados.');
    }

    public function senha(Request $request)
    {
        $dados = $request->validate([
            'senha_atual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'senha_atual.current_password' => 'A senha atual está incorreta.',
        ], ['senha_atual' => 'senha atual', 'password' => 'nova senha']);

        // O cast 'hashed' do User já criptografa.
        $request->user()->update(['password' => $dados['password']]);

        return back()->with('success', 'Senha alterada.');
    }

    public function fiscal(Request $request)
    {
        $request->merge([
            'cnpj' => preg_replace('/\D/', '', (string) $request->input('cnpj')) ?: null,
        ]);

        $dados = $request->validate([
            'razao_social' => ['nullable', 'string', 'max:150'],
            'cnpj' => ['nullable', 'digits:14'],
            'inscricao_estadual' => ['nullable', 'string', 'max:20'],
            'crt' => ['required', Rule::in([1, 2, 3, 4])],
            'ambiente' => ['required', Rule::in([1, 2])],
            'api_token' => ['nullable', 'string', 'max:500'],
        ], [], ['cnpj' => 'CNPJ', 'crt' => 'regime tributário', 'api_token' => 'token']);

        // Campo vazio = manter o token que já está salvo.
        if (blank($dados['api_token'] ?? null)) {
            unset($dados['api_token']);
        }

        ConfiguracaoFiscal::atual()->update($dados);

        return back()->with('success', 'Configuração fiscal salva.');
    }

        public function empresa(Request $request)
    {
        $dados = $request->validate([
            'empresa' => ['required', 'string', 'max:100'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remover_foto' => ['boolean'],
        ], [
            'foto.image' => 'Envie uma imagem (JPG, PNG ou WEBP).',
            'foto.mimes' => 'Envie uma imagem (JPG, PNG ou WEBP).',
            'foto.max' => 'A imagem pode ter no máximo 2 MB.',
        ], ['empresa' => 'nome da empresa']);

        $user = $request->user();
        $disco = Storage::disk('public');

        if ($request->hasFile('foto') || $request->boolean('remover_foto')) {
            // Apaga o logo antigo para não acumular arquivos.
            if ($user->foto_path) {
                $disco->delete($user->foto_path);
            }

            $user->foto_path = $request->hasFile('foto')
                ? $request->file('foto')->store('logos', 'public')
                : null;
        }

        $user->empresa = $dados['empresa'];
        $user->save();

        return back()->with('success', 'Dados da empresa atualizados.');
    }
}

