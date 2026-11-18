<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Entrada;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Venda;
use App\Fiscal\BrasilNfeEmissor;
use App\Fiscal\EmissorFiscal;
use App\Models\OrdemServico;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmissorFiscal::class, BrasilNfeEmissor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Em produção todos os links gerados usam https (o app fica atrás de um proxy).
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Só administrador gerencia as contas de acesso.
        Gate::define('administrar', fn (User $user) => $user->isAdministrador());

        Relation::morphMap([
            'entrada' => Entrada::class,
            'venda' => Venda::class,
            'ordem_servico' => OrdemServico::class,
        ]);
    }
}
