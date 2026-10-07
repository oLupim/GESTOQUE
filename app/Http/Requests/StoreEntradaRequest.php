<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Aceita vírgula decimal nos itens ("14,50" → "14.50"). */
    protected function prepareForValidation(): void
    {
        $decimal = fn ($v) => $v === null || $v === '' ? null : str_replace(',', '.', (string) $v);

        $this->merge([
            'itens' => collect($this->input('itens', []))->map(fn ($i) => [
                'produto_id' => $i['produto_id'] ?? null,
                'quantidade' => $decimal($i['quantidade'] ?? null),
                'custo_unitario' => $decimal($i['custo_unitario'] ?? null),
            ])->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'fornecedor' => ['nullable', 'string', 'max:120'],
            'documento' => ['nullable', 'string', 'max:60'],
            'data_entrada' => ['required', 'date', 'before_or_equal:today'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'distinct', 'exists:produtos,id'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'itens.*.custo_unitario' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_entrada.required' => 'Informe a data da entrada.',
            'data_entrada.before_or_equal' => 'A data não pode ser futura.',
            'itens.required' => 'Adicione ao menos um produto.',
            'itens.min' => 'Adicione ao menos um produto.',
            'itens.*.produto_id.required' => 'Escolha o produto.',
            'itens.*.produto_id.distinct' => 'Produto repetido na entrada.',
            'itens.*.produto_id.exists' => 'Produto não encontrado.',
            'itens.*.quantidade.required' => 'Informe a quantidade.',
            'itens.*.quantidade.numeric' => 'Quantidade inválida.',
            'itens.*.quantidade.gt' => 'Quantidade deve ser maior que zero.',
            'itens.*.custo_unitario.numeric' => 'Custo inválido.',
            'itens.*.custo_unitario.min' => 'Custo não pode ser negativo.',
        ];
    }
}