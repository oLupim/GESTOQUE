<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Testes que usam o banco já começam logados como administrador. */
    protected bool $logado = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->logado && in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->actingAs(User::factory()->create(['perfil' => 'administrador']));
        }
    }
}