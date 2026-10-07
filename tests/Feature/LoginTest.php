<?php

namespace Tests\Feature;

use App\Models\Movimentacao;
use App\Models\Produto;
use App\Models\User;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected bool $logado = false;

    public function test_visitante_e_mandado_para_o_login(): void
    {
        $this->get('/produtos')->assertRedirect('/login');
        $this->post('/vendas', [])->assertRedirect('/login');
    }

    public function test_login_com_senha_certa_entra(): void
    {
        $u = User::factory()->create(['password' => 'segredo123']);

        $this->post('/login', ['email' => $u->email, 'password' => 'segredo123'])->assertRedirect('/produtos');
        $this->assertAuthenticatedAs($u);
    }

    public function test_senha_errada_nao_entra(): void
    {
        $u = User::factory()->create(['password' => 'segredo123']);

        $this->post('/login', ['email' => $u->email, 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_desativado_nao_entra(): void
    {
        $u = User::factory()->create(['password' => 'segredo123', 'ativo' => false]);

        $this->post('/login', ['email' => $u->email, 'password' => 'segredo123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_movimentacao_registra_quem_fez(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $p = Produto::factory()->create();

        app(EstoqueService::class)->entrar($p, 3);

        $this->assertSame($u->id, Movimentacao::firstOrFail()->user_id);
    }
}