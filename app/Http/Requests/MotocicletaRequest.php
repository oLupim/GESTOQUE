<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MotocicletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'placa' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->placa)),
            'km_atual' => blank($this->km_atual) ? 0 : preg_replace('/\D/', '', (string) $this->km_atual),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('motocicleta')?->id;

        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            // Antiga (ABC1234) ou Mercosul (ABC1D23).
            'placa' => ['required', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', Rule::unique('motocicletas', 'placa')->ignore($id)],
            'marca' => ['required', 'string', 'max:40'],
            'modelo' => ['required', 'string', 'max:60'],
            'ano' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
            'cor' => ['nullable', 'string', 'max:30'],
            'km_atual' => ['nullable', 'integer', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Escolha o proprietário.',
            'placa.required' => 'Informe a placa.',
            'placa.regex' => 'Placa inválida. Use ABC1234 ou ABC1D23.',
            'placa.unique' => 'Esta placa já está cadastrada.',
            'marca.required' => 'Informe a marca.',
            'modelo.required' => 'Informe o modelo.',
            'ano.between' => 'Ano inválido.',
        ];
    }
}