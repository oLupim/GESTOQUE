<?php

use App\Models\Produto;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Inicio', [
    'produtos' => Produto::count(),
]));