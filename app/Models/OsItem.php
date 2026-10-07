<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OsItem extends Model
{
    protected $table = 'os_itens';

    protected $fillable = ['ordem_servico_id', 'produto_id', 'quantidade', 'preco_unitario', 'custo_unitario', 'subtotal', 'movimentacao_id'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:2',
            'custo_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function movimentacao(): BelongsTo
    {
        return $this->belongsTo(Movimentacao::class);
    }
}