<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Dados da oficina: registro único, editado pelo administrador em Configurações → Empresa.
 * Aparecem no menu, no topo do sistema e no catálogo público.
 */
class Oficina extends Model
{
    protected $table = 'oficina';

    protected $fillable = ['nome', 'logo_path', 'whatsapp', 'endereco'];

    public static function atual(): self
    {
        return self::firstOrCreate([]);
    }

    /** Nome salvo ou, se ainda não tiver, o padrão do config (OFICINA_NOME). */
    public function nomeExibicao(): string
    {
        return $this->nome ?: (string) config('gestoque.oficina');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? Storage::disk(config('gestoque.disco_imagens'))->url($this->logo_path)
            : null;
    }

    /** Muda quando o logo muda: vai no endereço do ícone para o celular não ficar com o antigo guardado. */
    public function versaoIcone(): string
    {
        return substr(md5($this->logo_path ?? 'gestoque'), 0, 8);
    }

    /** Formato enviado para as telas (menu, topo, configurações e catálogo). */
    public function paraTela(): array
    {
        return [
            'nome' => $this->nomeExibicao(),
            'logo_url' => $this->logoUrl(),
            'whatsapp' => $this->whatsapp,
            'endereco' => $this->endereco,
        ];
    }
}
