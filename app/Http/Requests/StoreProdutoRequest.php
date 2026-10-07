<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProdutoRequest extends FormRequest
{
    public const UNIDADES = ['UN', 'PAR', 'KIT', 'JG', 'L', 'ML', 'KG', 'M', 'FR', 'CX'];

    public function authorize(): bool
    {
        return true; // permissões entram junto com o login
    }

    /** Limpa a entrada antes de validar: "2710.19.32" → "27101932", "42,50" → "42.50". */
    protected function prepareForValidation(): void
    {
        $soDigitos = fn ($v) => $v === null || $v === '' ? null : preg_replace('/\D/', '', (string) $v);
        $decimal = fn ($v) => $v === null || $v === '' ? null : str_replace(',', '.', (string) $v);

        $this->merge([
            'codigo' => strtoupper(trim((string) $this->codigo)),
            'ncm' => $soDigitos($this->ncm),
            'cfop' => $soDigitos($this->cfop),
            'codigo_barras' => $soDigitos($this->codigo_barras),
            'preco_custo' => $decimal($this->preco_custo),
            'preco_venda' => $decimal($this->preco_venda),
            'estoque_minimo' => $decimal($this->estoque_minimo),
            'estoque_inicial' => $decimal($this->estoque_inicial),
        ]);
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', 'unique:produtos,codigo'],
            'codigo_barras' => ['nullable', 'digits_between:8,14', 'unique:produtos,codigo_barras'],
            'nome' => ['required', 'string', 'max:120'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'marca' => ['nullable', 'string', 'max:60'],
            'unidade' => ['required', 'in:'.implode(',', self::UNIDADES)],
            'preco_custo' => ['nullable', 'numeric', 'min:0'],
            'preco_venda' => ['required', 'numeric', 'gt:0'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0'],
            'estoque_inicial' => ['nullable', 'numeric', 'min:0'],
            'ncm' => ['nullable', 'digits:8'],
            'cfop' => ['nullable', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Informe :attribute.',
            'unique' => 'Já existe um produto com este :attribute.',
            'numeric' => ':Attribute deve ser um número.',
            'min' => ':Attribute não pode ser negativo.',
            'gt' => ':Attribute deve ser maior que zero.',
            'max' => ':Attribute pode ter no máximo :max caracteres.',
            'digits' => ':Attribute deve ter :digits dígitos.',
            'digits_between' => ':Attribute deve ter entre :min e :max dígitos.',
            'in' => ':Attribute inválida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'o código',
            'codigo_barras' => 'o código de barras',
            'nome' => 'o nome',
            'unidade' => 'a unidade',
            'preco_custo' => 'o custo',
            'preco_venda' => 'o preço de venda',
            'estoque_minimo' => 'o estoque mínimo',
            'estoque_inicial' => 'a quantidade inicial',
            'ncm' => 'o NCM',
            'cfop' => 'o CFOP',
        ];
    }
}