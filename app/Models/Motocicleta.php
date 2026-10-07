<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Motocicleta extends Model
{
    protected $table = 'motocicletas';

    protected $fillable = ['cliente_id', 'placa', 'marca', 'modelo', 'ano', 'cor', 'km_atual', 'observacoes'];

    protected function casts(): array
    {
        return ['ano' => 'integer', 'km_atual' => 'integer'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}