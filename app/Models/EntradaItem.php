<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntradaItem extends Model
{
    // Plural em português (o Laravel geraria "entrada_items").
    protected $table = 'entrada_itens';

    protected $fillable = ['entrada_id', 'produto_id', 'quantidade', 'custo_unitario'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'custo_unitario' => 'decimal:2',
        ];
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(Entrada::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}