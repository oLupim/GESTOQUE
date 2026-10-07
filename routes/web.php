<?php

use App\Http\Controllers\ProdutoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\EstoqueController;
use App\Http\Controllers\EntradaController;
use App\Http\Controllers\VendaController;

Route::redirect('/', '/produtos');

Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');

Route::post('/produtos', [ProdutoController::class, 'store'])->name('produtos.store');

Route::get('/estoque', [EstoqueController::class, 'index'])->name('estoque.index');

Route::post('/estoque/movimentacoes', [EstoqueController::class, 'store'])->name('estoque.movimentar');

Route::get('/entradas', [EntradaController::class, 'index'])->name('entradas.index');

Route::post('/entradas', [EntradaController::class, 'store'])->name('entradas.store');

Route::post('/vendas', [VendaController::class, 'store'])->name('vendas.store');

Route::post('/vendas/{venda}/cancelar', [VendaController::class, 'cancelar'])->name('vendas.cancelar');

Route::get('/vendas', [VendaController::class, 'index'])->name('vendas.index');

Route::get('/vendas/nova', [VendaController::class, 'create'])->name('vendas.create');