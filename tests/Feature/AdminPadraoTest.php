<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPadraoTest extends TestCase
{
    use RefreshDatabase;

    // Começa sem ninguém logado e sem nenhum usuário no banco.
    protected bool $logado = false;

    private function configurar(?string $email, ?string $senha): void
    {
        config([
            'gestoque.admin.nome' => 'Dono da Oficina',
            'gestoque.admin.email' => $email,
            'gestoque.admin.senha' => $senha,
        ]);
    }

    public function test_cria_o_admin_quando_nao_existe_nenhum(): void
    {
        $this->configurar('dono@oficina.com', 'senhaForte1');

        $this->artisan('gestoque:admin-padrao')->assertSuccessful();

        $admin = User::where('email', 'dono@oficina.com')->firstOrFail();
        $this->assertSame('administrador', $admin->perfil);
        $this->assertTrue($admin->ativo);
        $this->assertTrue(Hash::check('senhaForte1', $admin->password));
    }

    public function test_rodar_de_novo_nao_duplica_nem_troca_a_senha(): void
    {
        $this->configurar('dono@oficina.com', 'senhaForte1');
        $this->artisan('gestoque:admin-padrao')->assertSuccessful();

        // Simula o dono trocando a senha pela tela e um novo deploy rodando o comando.
        User::first()->update(['password' => 'senhaNovaDoDono']);
        $this->artisan('gestoque:admin-padrao')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('senhaNovaDoDono', User::first()->password));
    }

    public function test_nao_cria_se_ja_existe_outro_administrador(): void
    {
        User::factory()->create(['perfil' => 'administrador']);
        $this->configurar('dono@oficina.com', 'senhaForte1');

        $this->artisan('gestoque:admin-padrao')->assertSuccessful();

        $this->assertFalse(User::where('email', 'dono@oficina.com')->exists());
    }

    public function test_sem_variaveis_nao_faz_nada(): void
    {
        $this->configurar(null, null);

        $this->artisan('gestoque:admin-padrao')->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_senha_curta_falha(): void
    {
        $this->configurar('dono@oficina.com', '123');

        $this->artisan('gestoque:admin-padrao')->assertFailed();

        $this->assertSame(0, User::count());
    }
}
