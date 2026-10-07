<?php

use App\Http\Controllers\ProdutoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/produtos');

Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');

Route::post('/produtos', [ProdutoController::class, 'store'])->name('produtos.store');

// Módulos ainda não implementados.
Route::get('/{modulo}', fn (string $modulo) => Inertia::render('EmConstrucao', ['modulo' => $modulo]))
    ->whereIn('modulo', [
        'dashboard', 'vendas', 'estoque', 'entradas', 'servicos', 'clientes',
        'motocicletas', 'fiscal', 'relatorios', 'configuracoes', 'notificacoes',
    ]);
    
    