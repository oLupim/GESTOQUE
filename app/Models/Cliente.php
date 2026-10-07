<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['nome', 'cpf_cnpj', 'telefone', 'email', 'endereco', 'observacoes'];

    public function motocicletas(): HasMany
    {
        return $this->hasMany(Motocicleta::class)->orderBy('modelo');
    }
}