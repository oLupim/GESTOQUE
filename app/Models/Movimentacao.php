<?php

namespace App\Models;

use App\Enums\TipoMovimentacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class Movimentacao extends Model
{
    protected $table = 'movimentacoes';

    protected $fillable = [
        'produto_id', 'tipo', 'quantidade', 'saldo_anterior', 'saldo_posterior',
        'origem_type', 'origem_id', 'estorno_de_id', 'motivo', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacao::class,
            'quantidade' => 'decimal:3',
            'saldo_anterior' => 'decimal:3',
            'saldo_posterior' => 'decimal:3',
        ];
    }

    /** Histórico imutável: correção é sempre um novo lançamento (estorno ou ajuste). */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Movimentações de estoque não podem ser alteradas.'));
        static::deleting(fn () => throw new LogicException('Movimentações de estoque não podem ser excluídas.'));
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function origem(): MorphTo
    {
        return $this->morphTo();
    }

    public function estornadaPor(): HasOne
    {
        return $this->hasOne(self::class, 'estorno_de_id');
    }
}