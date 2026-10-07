<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracaoFiscal extends Model
{
    public const HOMOLOGACAO = 2;
    public const PRODUCAO = 1;

    protected $table = 'configuracao_fiscal';

    protected $fillable = ['razao_social', 'cnpj', 'inscricao_estadual', 'crt', 'ambiente', 'api_token'];

    protected $hidden = ['api_token'];

    protected function casts(): array
    {
        return [
            'crt' => 'integer',
            'ambiente' => 'integer',
            'api_token' => 'encrypted', // criptografado com a APP_KEY
        ];
    }

    /** Sempre existe uma configuração (criada vazia, em homologação, na primeira leitura). */
    public static function atual(): self
    {
        return self::firstOrCreate([], ['ambiente' => self::HOMOLOGACAO]);
    }

    public function prontaParaEmitir(): bool
    {
        return filled($this->api_token) && filled($this->cnpj);
    }
}