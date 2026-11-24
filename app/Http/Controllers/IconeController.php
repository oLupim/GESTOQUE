<?php

namespace App\Http\Controllers;

use App\Models\Oficina;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ícone do "Adicionar à Tela de Início" (iPhone/Android) e da aba do navegador.
 * Usa o logo da oficina centralizado num quadrado branco; sem logo (ou se a imagem falhar), o "G" do Gestoque.
 * Público de propósito: o celular busca o ícone até na tela de login.
 */
class IconeController extends Controller
{
    public const TAMANHOS = [180, 192, 512]; // 180 = iPhone; 192 e 512 = Android/manifesto

    public function show(int $tamanho): Response
    {
        abort_unless(in_array($tamanho, self::TAMANHOS, true), 404);

        $oficina = Oficina::atual();

        if ($oficina->logo_path) {
            // Gera uma vez por logo e guarda; logo novo = caminho novo = chave nova.
            $chave = "icone:{$tamanho}:".md5($oficina->logo_path);
            $png = Cache::get($chave);

            if ($png === null && ($gerado = $this->doLogo($oficina->logo_path, $tamanho)) !== null) {
                $png = base64_encode($gerado);
                Cache::forever($chave, $png);
            }

            if ($png !== null) {
                return response(base64_decode($png), 200, $this->cabecalhos());
            }
        }

        return response()->file(public_path("icones/gestoque-{$tamanho}.png"), $this->cabecalhos());
    }

    /** Logo "cabe inteiro" no quadrado, com margem e fundo branco (o iPhone pinta a transparência de preto). */
    private function doLogo(string $caminho, int $tamanho): ?string
    {
        try {
            $origem = @imagecreatefromstring(Storage::disk(config('gestoque.disco_imagens'))->get($caminho) ?? '');
        } catch (\Throwable) {
            return null;
        }

        if (! $origem) {
            return null;
        }

        $quadro = imagecreatetruecolor($tamanho, $tamanho);
        imagefill($quadro, 0, 0, imagecolorallocate($quadro, 255, 255, 255));

        $largura = imagesx($origem);
        $altura = imagesy($origem);
        $util = $tamanho * 0.84; // margem de 8% de cada lado: o iPhone arredonda os cantos
        $escala = min($util / $largura, $util / $altura);
        $l = (int) round($largura * $escala);
        $a = (int) round($altura * $escala);

        imagecopyresampled($quadro, $origem, intdiv($tamanho - $l, 2), intdiv($tamanho - $a, 2), 0, 0, $l, $a, $largura, $altura);

        ob_start();
        imagepng($quadro, null, 9);
        $png = ob_get_clean();

        imagedestroy($origem);
        imagedestroy($quadro);

        return $png ?: null;
    }

    private function cabecalhos(): array
    {
        return ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400'];
    }
}
