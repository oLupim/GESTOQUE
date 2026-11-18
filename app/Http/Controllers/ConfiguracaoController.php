<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracaoFiscal;
use App\Models\Oficina;
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

    /** Dados da oficina (só administrador: a rota exige can:administrar). */
    public function empresa(Request $request)
    {
        // WhatsApp: guarda só os dígitos. Endereço vazio vira null.
        $request->merge([
            'whatsapp' => preg_replace('/\D/', '', (string) $request->input('whatsapp')) ?: null,
            'endereco' => trim((string) $request->input('endereco')) ?: null,
        ]);

        $dados = $request->validate([
            'empresa' => ['required', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'digits_between:12,13'],
            'endereco' => ['nullable', 'string', 'max:200'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remover_foto' => ['boolean'],
        ], [
            'whatsapp.digits_between' => 'Informe o WhatsApp com 55 + DDD + número. Ex.: 55 54 99999-9999.',
            // "uploaded" = o PHP recusou antes (arquivo maior que o limite do servidor).
            'foto.uploaded' => 'Não foi possível enviar a imagem. Tente uma foto menor.',
            'foto.image' => 'Envie uma imagem (JPG, PNG ou WEBP).',
            'foto.mimes' => 'Envie uma imagem (JPG, PNG ou WEBP).',
            'foto.max' => 'A imagem pode ter no máximo 2 MB.',
        ], ['empresa' => 'nome da empresa', 'endereco' => 'endereço']);

        $oficina = Oficina::atual();
        $nomeDisco = config('gestoque.disco_imagens');
        $disco = Storage::disk($nomeDisco);

        if ($request->hasFile('foto') || $request->boolean('remover_foto')) {
            // Apaga o logo antigo para não acumular arquivos.
            if ($oficina->logo_path) {
                $disco->delete($oficina->logo_path);
            }

            $oficina->logo_path = $request->hasFile('foto')
                ? $request->file('foto')->store('logos', $nomeDisco)
                : null;
        }

        $oficina->fill([
            'nome' => $dados['empresa'],
            'whatsapp' => $dados['whatsapp'],
            'endereco' => $dados['endereco'],
        ])->save();

        return back()->with('success', 'Dados da empresa atualizados.');
    }
}

