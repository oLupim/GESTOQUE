<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConfiguracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_altera_nome_e_email(): void
    {
        $this->put('/configuracoes/perfil', ['name' => 'Gustavo L', 'email' => 'novo@gestoque.test'])
            ->assertSessionHasNoErrors();

        $user = auth()->user()->fresh();
        $this->assertSame('Gustavo L', $user->name);
        $this->assertSame('novo@gestoque.test', $user->email);
    }

    public function test_email_de_outro_usuario_e_recusado(): void
    {
        User::factory()->create(['email' => 'outro@gestoque.test']);

        $this->put('/configuracoes/perfil', ['name' => 'X', 'email' => 'outro@gestoque.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_senha_atual_errada_e_recusada(): void
    {
        $this->put('/configuracoes/senha', [
            'senha_atual' => 'errada',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertSessionHasErrors('senha_atual');
    }

    public function test_troca_a_senha(): void
    {
        $this->put('/configuracoes/senha', [
            'senha_atual' => 'password',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('novaSenha123', auth()->user()->fresh()->password));
    }

    public function test_token_fica_criptografado_e_campo_vazio_nao_apaga(): void
    {
        $dados = ['cnpj' => '12.345.678/0001-90', 'crt' => 1, 'ambiente' => 2];

        $this->put('/configuracoes/fiscal', $dados + ['api_token' => 'TOKEN-TESTE'])->assertSessionHasNoErrors();
        $this->put('/configuracoes/fiscal', $dados + ['api_token' => ''])->assertSessionHasNoErrors();

        $config = ConfiguracaoFiscal::atual();
        $this->assertSame('12345678000190', $config->cnpj);
        $this->assertSame('TOKEN-TESTE', $config->api_token);
        $this->assertNotSame('TOKEN-TESTE', DB::table('configuracao_fiscal')->value('api_token'));
    }
}