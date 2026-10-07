<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrdemServico extends Model
{
    public const ABERTA = 'aberta';
    public const EM_ANDAMENTO = 'em_andamento';
    public const AGUARDANDO_PECA = 'aguardando_peca';
    public const FINALIZADA = 'finalizada';
    public const CANCELADA = 'cancelada';

    /** Rótulos iguais aos do protótipo (StatusBadge). */
    public const ROTULOS = [
        self::ABERTA => 'Aberto',
        self::EM_ANDAMENTO => 'Em andamento',
        self::AGUARDANDO_PECA => 'Aguardando peça',
        self::FINALIZADA => 'Finalizado',
        self::CANCELADA => 'Cancelado',
    ];

    /** Para onde cada status pode ir (cancelar é uma ação à parte). */
    public const TRANSICOES = [
        self::ABERTA => [self::EM_ANDAMENTO, self::AGUARDANDO_PECA, self::FINALIZADA],
        self::EM_ANDAMENTO => [self::AGUARDANDO_PECA, self::FINALIZADA],
        self::AGUARDANDO_PECA => [self::EM_ANDAMENTO, self::FINALIZADA],
        self::FINALIZADA => [],
        self::CANCELADA => [],
    ];

    protected $table = 'ordens_servico';

    protected $fillable = [
        'cliente_id', 'motocicleta_id', 'status', 'mecanico', 'km_entrada', 'problema', 'descricao_servico',
        'valor_mao_obra', 'valor_pecas', 'total', 'user_id', 'finalizada_em', 'cancelada_em', 'motivo_cancelamento',
    ];

    protected function casts(): array
    {
        return [
            'km_entrada' => 'integer',
            'valor_mao_obra' => 'decimal:2',
            'valor_pecas' => 'decimal:2',
            'total' => 'decimal:2',
            'finalizada_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function motocicleta(): BelongsTo
    {
        return $this->belongsTo(Motocicleta::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OsItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimentacoes(): MorphMany
    {
        return $this->morphMany(Movimentacao::class, 'origem');
    }

    public function editavel(): bool
    {
        return ! in_array($this->status, [self::FINALIZADA, self::CANCELADA], true);
    }

    public function rotulo(): string
    {
        return self::ROTULOS[$this->status] ?? $this->status;
    }
}