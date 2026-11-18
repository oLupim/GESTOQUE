<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_nao_cria_usuarios_de_exemplo_em_producao(): void
    {
        $this->app['env'] = 'production';
        $usuariosAntes = User::count();

        // --force pula a confirmação que o Laravel pede em produção (pior caso).
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame($usuariosAntes, User::count());
        $this->assertFalse(User::where('email', 'admin@gestoque.test')->exists());
        $this->assertSame(0, Produto::count());
    }

    public function test_seeder_cria_dados_de_exemplo_fora_de_producao(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::where('email', 'admin@gestoque.test')->exists());
    }

    public function test_rota_de_saude_responde(): void
    {
        $this->get('/up')->assertOk();
    }
}
