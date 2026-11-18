<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
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

    public function test_sem_empresa_salva_usa_nome_do_config_e_sem_foto(): void
    {
        $this->get('/configuracoes')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.empresa', config('gestoque.oficina'))
            ->where('auth.user.foto_url', null)
        );
    }

    public function test_salva_nome_e_logo_da_empresa(): void
    {
        Storage::fake('public');

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'foto' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Dados da empresa atualizados.');

        $user = auth()->user()->fresh();
        $this->assertSame('Oficina Teste', $user->empresa);
        $this->assertNotNull($user->foto_path);
        Storage::disk('public')->assertExists($user->foto_path);
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado(): void
    {
        Storage::fake('public');

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'foto' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('foto');

        $this->assertNull(auth()->user()->fresh()->foto_path);
    }

    public function test_remover_foto_apaga_o_arquivo(): void
    {
        Storage::fake('public');

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'foto' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $caminho = auth()->user()->fresh()->foto_path;
        Storage::disk('public')->assertExists($caminho);

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'remover_foto' => true,
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($caminho);
        $this->assertNull(auth()->user()->fresh()->foto_path);
    }
}