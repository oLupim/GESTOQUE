<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdemServicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {

        $this->merge([
            'valor_mao_obra' => blank($this->valor_mao_obra) ? 0 : str_replace(',', '.', (string) $this->valor_mao_obra),
            'km_entrada' => blank($this->km_entrada) ? null : preg_replace('/\D/', '', (string) $this->km_entrada),
            'pecas' => collect($this->input('pecas', []))->map(fn ($p) => [
                'produto_id' => $p['produto_id'] ?? null,
                'quantidade' => str_replace(',', '.', (string) ($p['quantidade'] ?? '')),
            ])->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'motocicleta_id' => ['required', 'exists:motocicletas,id'],
            'mecanico' => ['nullable', 'string', 'max:60'],
            'km_entrada' => ['nullable', 'integer', 'min:0'],
            'problema' => ['nullable', 'string', 'max:2000'],
            'descricao_servico' => ['nullable', 'string', 'max:200'],
            'valor_mao_obra' => ['nullable', 'numeric', 'min:0'],
            'pecas' => ['nullable', 'array'],
            'pecas.*.produto_id' => ['required', 'exists:produtos,id'],
            'pecas.*.quantidade' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Escolha o cliente.',
            'motocicleta_id.required' => 'Escolha a motocicleta.',
            'valor_mao_obra.numeric' => 'Valor da mão de obra inválido.',
            'pecas.*.quantidade.gt' => 'Quantidade deve ser maior que zero.',
        ];
    }
}