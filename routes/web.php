<?php

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\EntradaController;
use App\Http\Controllers\EstoqueController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\VendaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\MotocicletaController;
use App\Http\Controllers\OrdemServicoController;

// Pública: clientes veem as peças disponíveis sem login.
Route::get('/catalogo', [CatalogoController::class, 'index'])->name('catalogo');

   Route::middleware('guest')->group(function () {
       Route::get('/login', [LoginController::class, 'create'])->name('login');
       Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
   });


   Route::middleware('auth')->group(function () {
       Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

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

        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');

        Route::get('/motocicletas', [MotocicletaController::class, 'index'])->name('motocicletas.index');
        Route::post('/motocicletas', [MotocicletaController::class, 'store'])->name('motocicletas.store');
        Route::put('/motocicletas/{motocicleta}', [MotocicletaController::class, 'update'])->name('motocicletas.update');


        Route::get('/servicos', [OrdemServicoController::class, 'index'])->name('servicos.index');
        Route::post('/servicos', [OrdemServicoController::class, 'store'])->name('servicos.store');
        Route::get('/servicos/{os}', [OrdemServicoController::class, 'show'])->name('servicos.show');
        Route::put('/servicos/{os}', [OrdemServicoController::class, 'update'])->name('servicos.update');
        Route::post('/servicos/{os}/status', [OrdemServicoController::class, 'status'])->name('servicos.status');
        Route::post('/servicos/{os}/cancelar', [OrdemServicoController::class, 'cancelar'])->name('servicos.cancelar');
        Route::post('/servicos/{os}/pecas', [OrdemServicoController::class, 'adicionarPeca'])->name('servicos.pecas.store');
        Route::delete('/servicos/{os}/pecas/{item}', [OrdemServicoController::class, 'removerPeca'])->name('servicos.pecas.destroy');
   });