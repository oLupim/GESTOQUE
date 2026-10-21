<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CriarUsuarioTest extends TestCase
{
    use RefreshDatabase;

    protected bool $logado = false;

    public function test_cria_conta_ativa_com_senha_criptografada(): void
    {
        $this->artisan('gestoque:usuario', [
            '--nome' => 'Lupim', '--email' => 'dono@oficina.com', '--senha' => 'senhaForte1',
        ])->assertSuccessful();

        $user = User::where('email', 'dono@oficina.com')->firstOrFail();
        $this->assertTrue($user->ativo);
        $this->assertSame('administrador', $user->perfil);
        $this->assertTrue(Hash::check('senhaForte1', $user->password));
    }

    public function test_email_repetido_falha(): void
    {
        User::factory()->create(['email' => 'dono@oficina.com']);

        $this->artisan('gestoque:usuario', [
            '--nome' => 'Outro', '--email' => 'dono@oficina.com', '--senha' => 'senhaForte1',
        ])->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_senha_curta_falha(): void
    {
        $this->artisan('gestoque:usuario', [
            '--nome' => 'Lupim', '--email' => 'dono@oficina.com', '--senha' => '123',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }
}