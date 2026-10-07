<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $digitos = fn ($v) => blank($v) ? null : preg_replace('/\D/', '', (string) $v);

        $this->merge([
            'nome' => trim((string) $this->nome),
            'cpf_cnpj' => $digitos($this->cpf_cnpj),
            'telefone' => $digitos($this->telefone),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('cliente')?->id; // na edição, ignora o próprio registro

        return [
            'nome' => ['required', 'string', 'max:120'],
            'cpf_cnpj' => ['nullable', 'regex:/^(\d{11}|\d{14})$/', Rule::unique('clientes', 'cpf_cnpj')->ignore($id)],
            'telefone' => ['nullable', 'digits_between:10,11'],
            'email' => ['nullable', 'email', 'max:120'],
            'endereco' => ['nullable', 'string', 'max:200'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome.',
            'cpf_cnpj.regex' => 'CPF deve ter 11 dígitos e CNPJ 14.',
            'cpf_cnpj.unique' => 'Já existe um cliente com este CPF/CNPJ.',
            'telefone.digits_between' => 'Telefone deve ter DDD + número (10 ou 11 dígitos).',
            'email.email' => 'E-mail inválido.',
        ];
    }
}