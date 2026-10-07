<?php

namespace App\Fiscal;

use App\Models\ConfiguracaoFiscal;

/**
 * Contrato que qualquer provedor fiscal precisa cumprir.
 * O resto do sistema só conhece esta interface, nunca o provedor.
 */
interface EmissorFiscal
{
    /**
     * @param  array<string, mixed>  $nota  Dados da nota no formato do Gestoque
     *
     * @throws \Illuminate\Http\Client\ConnectionException quando não há comunicação
     */
    public function emitir(array $nota, ConfiguracaoFiscal $config): ResultadoEmissao;
}