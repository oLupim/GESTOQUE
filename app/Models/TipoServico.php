<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Serviço padrão da oficina com código interno (SRV-001 Troca de óleo), sugerido ao abrir/editar uma OS. */
class TipoServico extends Model
{
    protected $table = 'tipos_servico';

    protected $fillable = ['codigo', 'nome', 'categoria', 'valor_sugerido', 'ativo'];

    protected function casts(): array
    {
        return ['valor_sugerido' => 'decimal:2', 'ativo' => 'boolean'];
    }

    /** Lista para as sugestões da tela de OS. */
    public static function paraSugestao(): array
    {
        return static::where('ativo', true)->orderBy('codigo')->get()
            ->map(fn (self $t) => [
                'codigo' => $t->codigo,
                'nome' => $t->nome,
                'categoria' => $t->categoria,
                'valor_sugerido' => $t->valor_sugerido !== null ? (float) $t->valor_sugerido : null,
            ])->all();
    }
}
