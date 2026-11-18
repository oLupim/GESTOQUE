<?php

namespace App\Http\Requests;

use App\Models\Produto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProdutoRequest extends FormRequest
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

    /**
     * Serve para cadastrar e para editar. Na edição: o código pode repetir o do próprio produto,
     * não existe "quantidade inicial" (estoque só muda por movimentação) e dá para ativar/desativar.
     */
    public function rules(): array
    {
        $produto = $this->route('produto');

        $regras = [
            'codigo' => ['required', 'string', 'max:30', Rule::unique('produtos', 'codigo')->ignore($produto?->id)],
            'codigo_barras' => ['nullable', 'digits_between:8,14', Rule::unique('produtos', 'codigo_barras')->ignore($produto?->id)],
            'nome' => ['required', 'string', 'max:120'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'marca' => ['nullable', 'string', 'max:60'],
            'unidade' => ['required', 'in:'.implode(',', self::UNIDADES)],
            'preco_custo' => ['nullable', 'numeric', 'min:0'],
            'preco_venda' => ['required', 'numeric', 'gt:0'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0'],
            'ncm' => ['nullable', 'digits:8'],
            'cfop' => ['nullable', 'digits:4'],
            'foto' => Produto::REGRAS_FOTO,
        ];

        if ($produto) {
            $regras['ativo'] = ['boolean'];
            $regras['remover_foto'] = ['boolean'];
        } else {
            $regras['estoque_inicial'] = ['nullable', 'numeric', 'min:0'];
        }

        return $regras;
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
            ...Produto::MENSAGENS_FOTO, // mais específicas que as genéricas acima
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