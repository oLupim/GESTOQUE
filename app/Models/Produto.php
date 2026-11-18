<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'produtos';

    // "saldo" fica de fora de propósito: só o serviço de estoque vai alterar.
    protected $fillable = [
        'codigo', 'codigo_barras', 'nome', 'descricao', 'categoria', 'marca', 'unidade',
        'preco_custo', 'preco_venda', 'estoque_minimo',
        'ncm', 'cfop', 'cest', 'origem', 'ativo', 'foto_path',
    ];

    /** Validação da foto, usada no cadastro e na troca de foto. */
    public const REGRAS_FOTO = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    public const MENSAGENS_FOTO = [
        'foto.uploaded' => 'Não foi possível enviar a foto. Tente uma foto menor.',
        'foto.image' => 'Envie uma imagem (JPG, PNG ou WEBP).',
        'foto.mimes' => 'Envie uma imagem (JPG, PNG ou WEBP).',
        'foto.max' => 'A foto pode ter no máximo 4 MB.',
    ];

    /** Endereço público da foto (bucket de imagens) ou null. */
    public function fotoUrl(): ?string
    {
        return $this->foto_path
            ? Storage::disk(config('gestoque.disco_imagens'))->url($this->foto_path)
            : null;
    }

    protected function casts(): array
    {
        return [
            'preco_custo' => 'decimal:2',
            'preco_venda' => 'decimal:2',
            'saldo' => 'decimal:3',
            'estoque_minimo' => 'decimal:3',
            'origem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class)->latest('id');
    }
    
    public function situacaoEstoque(): string
    {
        $saldo = (float) $this->saldo;
        $minimo = (float) $this->estoque_minimo;

        return match (true) {
            $saldo <= 0 => 'Sem estoque',
            $saldo <= floor($minimo / 2) => 'Crítico',
            $saldo <= $minimo => 'Baixo',
            default => 'Normal',
        };
    }
}