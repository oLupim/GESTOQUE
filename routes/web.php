<?php

use App\Http\Controllers\ProdutoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\EstoqueController;

Route::redirect('/', '/produtos');

Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');

Route::post('/produtos', [ProdutoController::class, 'store'])->name('produtos.store');

Route::get('/estoque', [EstoqueController::class, 'index'])->name('estoque.index');

Route::post('/estoque/movimentacoes', [EstoqueController::class, 'store'])->name('estoque.movimentar');