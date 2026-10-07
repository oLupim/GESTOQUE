<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venda extends Model
{
    public const CONFIRMADA = 'confirmada';
    public const CANCELADA = 'cancelada';

    public const FORMAS_PAGAMENTO = ['pix' => 'PIX', 'dinheiro' => 'Dinheiro', 'debito' => 'Débito', 'credito' => 'Crédito'];

    protected $table = 'vendas';

    protected $fillable = [
        'status', 'forma_pagamento', 'subtotal', 'desconto', 'total',
        'user_id', 'cancelada_em', 'motivo_cancelamento',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'desconto' => 'decimal:2',
            'total' => 'decimal:2',
            'cancelada_em' => 'datetime',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimentacoes(): MorphMany
    {
        return $this->morphMany(Movimentacao::class, 'origem');
    }

    public function estaCancelada(): bool
    {
        return $this->status === self::CANCELADA;
    }

    public function nfce(): HasOne
    {
        return $this->hasOne(DocumentoFiscal::class)->where('modelo', DocumentoFiscal::NFCE);
    }
}