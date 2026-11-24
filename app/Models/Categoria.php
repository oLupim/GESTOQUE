<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Categoria de produto ou de serviço. As de produto têm um prefixo para o código interno
 * (OLE → OLE-001, OLE-002...) e sugestões de unidade/NCM/CFOP para agilizar o cadastro.
 */
class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = ['nome', 'tipo', 'prefixo', 'unidade', 'ncm', 'cfop', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function scopeDeProduto(Builder $q): Builder
    {
        return $q->where('tipo', 'produto')->where('ativo', true);
    }

    public function scopeDeServico(Builder $q): Builder
    {
        return $q->where('tipo', 'servico')->where('ativo', true);
    }

    /** Categoria de produto pelo nome, sem diferenciar maiúsculas ("óleos e lubrificantes" acha "Óleos e lubrificantes"). */
    public static function produtoPorNome(?string $nome): ?self
    {
        $nome = trim((string) $nome);

        if ($nome === '') {
            return null;
        }

        return static::deProduto()->get()
            ->first(fn (self $c) => mb_strtolower($c->nome) === mb_strtolower($nome));
    }

    /**
     * Próximo código livre para um prefixo: maior número já usado + 1, com 3 dígitos.
     * Ex.: existem OLE-001 e OLE-007 → OLE-008. Códigos fora do padrão (OLE-ABC) são ignorados.
     */
    public static function proximoCodigo(string $prefixo): string
    {
        $prefixo = strtoupper($prefixo);

        $maior = Produto::where('codigo', 'like', "{$prefixo}-%")->pluck('codigo')
            ->map(fn ($codigo) => preg_match('/^'.preg_quote($prefixo, '/').'-(\d+)$/', $codigo, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return sprintf('%s-%03d', $prefixo, $maior + 1);
    }

    /** Dados para o formulário de produto (inclui o próximo código de cada categoria). */
    public function paraFormulario(): array
    {
        return [
            'nome' => $this->nome,
            'prefixo' => $this->prefixo,
            'unidade' => $this->unidade,
            'ncm' => $this->ncm,
            'cfop' => $this->cfop,
            'proximo_codigo' => $this->prefixo ? static::proximoCodigo($this->prefixo) : null,
        ];
    }
}
