<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A rota já exige administrador (middleware can:administrar).
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->name),
            'email' => mb_strtolower(trim((string) $this->email)),
        ]);
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'perfil' => ['required', Rule::in(array_keys(User::PERFIS))],
            'ativo' => ['boolean'],
            // Na criação a senha é obrigatória; na edição, vazia = manter a atual.
            'password' => [$usuario ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    /** Ninguém pode se desativar nem tirar o próprio perfil de administrador. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $usuario = $this->route('usuario');

                if (! $usuario || $usuario->id !== $this->user()->id) {
                    return;
                }
                if (! $this->boolean('ativo', true)) {
                    $validator->errors()->add('ativo', 'Você não pode desativar a sua própria conta.');
                }
                if ($this->input('perfil') !== 'administrador') {
                    $validator->errors()->add('perfil', 'Você não pode remover o seu próprio perfil de administrador.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'E-mail inválido.',
            'email.unique' => 'Já existe uma conta com este e-mail.',
            'perfil.in' => 'Perfil inválido.',
            'password.required' => 'Informe a senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
        ];
    }
}
