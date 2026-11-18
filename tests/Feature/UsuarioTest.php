<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return [
            'name' => 'Pedro Balcão',
            'email' => 'pedro@oficina.com',
            'perfil' => 'balconista',
            'password' => 'senhaForte1',
            'password_confirmation' => 'senhaForte1',
            ...$extra,
        ];
    }

    public function test_administrador_ve_a_lista_de_usuarios(): void
    {
        $this->get('/usuarios')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Usuarios/Index')
            ->has('usuarios', 1)
            ->has('perfis')
        );
    }

    public function test_administrador_cria_conta_ativa_com_senha_criptografada(): void
    {
        $this->post('/usuarios', $this->dados())->assertSessionHasNoErrors();

        $user = User::where('email', 'pedro@oficina.com')->firstOrFail();
        $this->assertSame('balconista', $user->perfil);
        $this->assertTrue($user->ativo);
        $this->assertTrue(Hash::check('senhaForte1', $user->password));
    }

    public function test_criacao_valida_email_repetido_senha_e_perfil(): void
    {
        User::factory()->create(['email' => 'pedro@oficina.com']);

        $this->post('/usuarios', $this->dados(['perfil' => 'dono', 'password_confirmation' => 'outra']))
            ->assertSessionHasErrors(['email', 'perfil', 'password']);
    }

    public function test_balconista_e_mecanico_nao_acessam_usuarios(): void
    {
        foreach (['balconista', 'mecanico'] as $perfil) {
            $this->actingAs(User::factory()->create(['perfil' => $perfil]));

            $this->get('/usuarios')->assertForbidden();
            $this->post('/usuarios', $this->dados(['email' => "{$perfil}@x.com"]))->assertForbidden();
        }

        $this->assertFalse(User::where('email', 'like', '%@x.com')->exists());
    }

    public function test_edicao_com_senha_vazia_mantem_a_senha(): void
    {
        $outro = User::factory()->create(['password' => 'senhaAntiga1', 'perfil' => 'mecanico']);

        $this->put("/usuarios/{$outro->id}", [
            'name' => 'Novo Nome', 'email' => $outro->email, 'perfil' => 'balconista', 'ativo' => true,
            'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $outro->refresh();
        $this->assertSame('Novo Nome', $outro->name);
        $this->assertSame('balconista', $outro->perfil);
        $this->assertTrue(Hash::check('senhaAntiga1', $outro->password));
    }

    public function test_desativa_outra_conta(): void
    {
        $outro = User::factory()->create(['perfil' => 'mecanico']);

        $this->put("/usuarios/{$outro->id}", [
            'name' => $outro->name, 'email' => $outro->email, 'perfil' => 'mecanico', 'ativo' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($outro->fresh()->ativo);
    }

    public function test_nao_pode_se_desativar_nem_tirar_o_proprio_admin(): void
    {
        $eu = auth()->user();

        $this->put("/usuarios/{$eu->id}", [
            'name' => $eu->name, 'email' => $eu->email, 'perfil' => 'balconista', 'ativo' => false,
        ])->assertSessionHasErrors(['ativo', 'perfil']);

        $eu->refresh();
        $this->assertTrue($eu->ativo);
        $this->assertSame('administrador', $eu->perfil);
    }

    public function test_conta_desativada_e_desconectada_na_proxima_acao(): void
    {
        $mecanico = User::factory()->create(['perfil' => 'mecanico']);
        $this->actingAs($mecanico);

        $mecanico->update(['ativo' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
