<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Entrada;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Venda;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'entrada' => Entrada::class,
            'venda' => Venda::class,
        ]);
    }
}
