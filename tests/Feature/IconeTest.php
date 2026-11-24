<?php

namespace Tests\Feature;

use App\Models\Oficina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IconeTest extends TestCase
{
    use RefreshDatabase;

    private function tamanhoDa(string $png): array
    {
        [$largura, $altura] = getimagesizefromstring($png);

        return [$largura, $altura];
    }

    public function test_sem_logo_usa_o_g_do_gestoque(): void
    {
        $resposta = $this->get('/icone/180.png')->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->assertSame(file_get_contents(public_path('icones/gestoque-180.png')), $resposta->streamedContent());
    }

    public function test_com_logo_gera_quadrado_no_tamanho_pedido_com_fundo_branco(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));
        // Logo largo (não quadrado): tem que caber inteiro, com sobra branca em cima e embaixo.
        $caminho = UploadedFile::fake()->image('logo.png', 400, 200)->store('logos', config('gestoque.disco_imagens'));
        Oficina::atual()->update(['logo_path' => $caminho]);

        $png = $this->get('/icone/512.png')->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();

        $this->assertSame([512, 512], $this->tamanhoDa($png));
        $imagem = imagecreatefromstring($png);
        $this->assertSame(0xFFFFFF, imagecolorat($imagem, 5, 5) & 0xFFFFFF); // canto branco, não transparente/preto
    }

    public function test_logo_que_nao_abre_volta_para_o_g(): void
    {
        Storage::fake(config('gestoque.disco_imagens'));
        Storage::disk(config('gestoque.disco_imagens'))->put('logos/quebrado.png', 'isto não é uma imagem');
        Oficina::atual()->update(['logo_path' => 'logos/quebrado.png']);

        $resposta = $this->get('/icone/192.png')->assertOk();

        $this->assertSame(file_get_contents(public_path('icones/gestoque-192.png')), $resposta->streamedContent());
    }

    public function test_tamanho_desconhecido_nao_existe(): void
    {
        $this->get('/icone/100.png')->assertNotFound();
    }

    public function test_paginas_apontam_para_o_icone_e_o_manifesto(): void
    {
        $this->withoutVite();
        $versao = Oficina::atual()->versaoIcone();

        // Até a tela de login (sem estar logado) precisa ter o ícone.
        auth()->logout();
        $this->get('/login')->assertOk()
            ->assertSee("/icone/180.png?v={$versao}", false)
            ->assertSee('rel="manifest"', false)
            ->assertSee('<meta name="apple-mobile-web-app-title" content="Gestoque">', false);

        $manifesto = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('Gestoque', $manifesto['short_name']);
        $this->assertSame(['/icone/192.png', '/icone/512.png'], array_column($manifesto['icons'], 'src'));
    }

    public function test_versao_do_icone_muda_quando_o_logo_muda(): void
    {
        $oficina = Oficina::atual();
        $antes = $oficina->versaoIcone();

        $oficina->update(['logo_path' => 'logos/novo.webp']);

        $this->assertNotSame($antes, $oficina->versaoIcone());
    }
}
