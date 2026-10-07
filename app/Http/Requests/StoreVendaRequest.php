<?php

namespace App\Http\Requests;

use App\Models\Venda;
use Illuminate\Foundation\Http\FormRequest;

class StoreVendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $decimal = fn ($v) => $v === null || $v === '' ? null : str_replace(',', '.', (string) $v);

        $this->merge([
            'desconto' => $decimal($this->desconto),
            'itens' => collect($this->input('itens', []))->map(fn ($i) => [
                'produto_id' => $i['produto_id'] ?? null,
                'quantidade' => $decimal($i['quantidade'] ?? null),
            ])->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'forma_pagamento' => ['required', 'in:'.implode(',', array_keys(Venda::FORMAS_PAGAMENTO))],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'distinct', 'exists:produtos,id'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'forma_pagamento.required' => 'Escolha a forma de pagamento.',
            'forma_pagamento.in' => 'Forma de pagamento inválida.',
            'desconto.numeric' => 'Desconto inválido.',
            'desconto.min' => 'O desconto não pode ser negativo.',
            'itens.required' => 'Adicione ao menos um produto.',
            'itens.min' => 'Adicione ao menos um produto.',
            'itens.*.produto_id.distinct' => 'Produto repetido na venda.',
            'itens.*.produto_id.exists' => 'Produto não encontrado.',
            'itens.*.quantidade.gt' => 'Quantidade deve ser maior que zero.',
        ];
    }
}