<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoFiscal extends Model
{
    public const NFCE = 65;
    public const NFE = 55;

    public const PENDENTE = 'pendente';
    public const PROCESSANDO = 'processando';
    public const AUTORIZADA = 'autorizada';
    public const REJEITADA = 'rejeitada';
    public const ERRO = 'erro';          // falha de comunicação: pode tentar de novo
    public const CANCELADA = 'cancelada';

    protected $table = 'documentos_fiscais';

    protected $fillable = [
        'venda_id', 'modelo', 'ambiente', 'status', 'numero', 'serie', 'chave', 'protocolo',
        'codigo_sefaz', 'mensagem', 'tentativas', 'xml_path', 'pdf_path', 'ultima_resposta', 'autorizada_em',
    ];

    protected function casts(): array
    {
        return [
            'ultima_resposta' => 'array',
            'autorizada_em' => 'datetime',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function podeEnviar(): bool
    {
        if ($this->status === self::PROCESSANDO) {
            // Proteção contra envio duplo; libera se travou há mais de 2 minutos.
            return $this->updated_at?->lt(now()->subMinutes(2)) ?? true;
        }

        return in_array($this->status, [self::PENDENTE, self::REJEITADA, self::ERRO], true);
    }
}