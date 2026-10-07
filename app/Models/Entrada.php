<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Entrada extends Model
{
    protected $table = 'entradas';

    protected $fillable = ['fornecedor', 'documento', 'data_entrada', 'observacao', 'user_id'];

    protected function casts(): array
    {
        return ['data_entrada' => 'date'];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(EntradaItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimentacoes(): MorphMany
    {
        return $this->morphMany(Movimentacao::class, 'origem');
    }
}