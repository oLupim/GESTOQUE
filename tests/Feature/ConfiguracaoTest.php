<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoFiscal;
use App\Models\Oficina;
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

    public function test_sem_empresa_salva_usa_nome_do_config_e_sem_logo(): void
    {
        $this->get('/configuracoes')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('oficina.nome', config('gestoque.oficina'))
            ->where('oficina.logo_url', null)
            ->where('oficina.whatsapp', null)
        );
    }

    public function test_salva_nome_logo_whatsapp_e_endereco_da_oficina(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'whatsapp' => '+55 (54) 99999-1234',
            'endereco' => 'Rua A, 10 - Nova Prata/RS',
            'foto' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Dados da empresa atualizados.');

        $oficina = Oficina::atual();
        $this->assertSame('Oficina Teste', $oficina->nome);
        $this->assertSame('5554999991234', $oficina->whatsapp);
        $this->assertSame('Rua A, 10 - Nova Prata/RS', $oficina->endereco);
        Storage::disk(config('gestoque.disco_imagens'))->assertExists($oficina->logo_path);
        $this->assertSame(1, Oficina::count());
    }

    public function test_whatsapp_sem_ddi_e_recusado(): void
    {
        $this->post('/configuracoes/empresa', ['empresa' => 'Oficina Teste', 'whatsapp' => '54 99999-1234'])
            ->assertSessionHasErrors('whatsapp');
    }

    public function test_mecanico_nao_altera_dados_da_oficina(): void
    {
        $this->actingAs(User::factory()->create(['perfil' => 'mecanico']));

        $this->post('/configuracoes/empresa', ['empresa' => 'Invasor'])->assertForbidden();

        $this->assertNotSame('Invasor', Oficina::atual()->nome);
    }

    public function test_mecanico_ve_nome_da_oficina_salvo_pelo_admin(): void
    {
        Oficina::atual()->update(['nome' => 'Oficina do Dono']);
        $this->actingAs(User::factory()->create(['perfil' => 'mecanico']));

        $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('oficina.nome', 'Oficina do Dono')
        );
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'foto' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('foto');

        $this->assertNull(Oficina::atual()->logo_path);
    }

    public function test_foto_recusada_pelo_servidor_mostra_mensagem_clara(): void
    {
        // Simula o PHP recusando um arquivo maior que o limite (upload_max_filesize).
        $arquivo = new UploadedFile(UploadedFile::fake()->image('grande.jpg')->getPathname(), 'grande.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->post('/configuracoes/empresa', ['empresa' => 'Oficina Teste', 'foto' => $arquivo])
            ->assertSessionHasErrors(['foto' => 'Não foi possível enviar a imagem. Tente uma foto menor.']);
    }

    public function test_remover_foto_apaga_o_arquivo(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'foto' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $caminho = Oficina::atual()->logo_path;
        Storage::disk(config('gestoque.disco_imagens'))->assertExists($caminho);

        $this->post('/configuracoes/empresa', [
            'empresa' => 'Oficina Teste',
            'remover_foto' => true,
        ])->assertSessionHasNoErrors();

        Storage::disk(config('gestoque.disco_imagens'))->assertMissing($caminho);
        $this->assertNull(Oficina::atual()->logo_path);
    }
}