<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Support\Str;

/**
 * Transforma o texto lido na foto da embalagem (OCR feito no celular) em sugestões de marca, categoria e nome.
 * São regras simples, sem IA e sem custo: marca = nome conhecido que aparece no texto;
 * categoria = palavras-chave; nome = linhas com letra maior. Tudo é sugestão: a pessoa confere antes de salvar.
 */
class InterpretarEmbalagem
{
    /** Marcas comuns em oficina de moto (além das que já estão cadastradas). */
    public const MARCAS_COMUNS = [
        'Motul', 'Mobil', 'Castrol', 'Shell', 'Petronas', 'Ipiranga', 'Lubrax', 'Havoline', 'Valvoline', 'Yamalube', 'Pro Honda',
        'Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'NGK', 'Denso', 'Bosch', 'Champion', 'Cobreq', 'Fras-le', 'Riffel', 'Vaz',
        'KMC', 'DID', 'Regina', 'Pirelli', 'Metzeler', 'Levorin', 'Michelin', 'Rinaldi', 'Technic', 'Maggion', 'Moura',
        'Heliar', 'Yuasa', 'Tecfil', 'Fram', 'Wega', 'Osram', 'Philips', 'Magnetron', 'Cofap', 'Sabó', 'Vedamotors', 'WD-40',
    ];

    /** Palavras-chave por categoria padrão (sem acento, maiúsculas). */
    public const PALAVRAS = [
        'Óleos e lubrificantes' => ['OLEO', 'LUBRIFICANTE', 'MOTOR OIL', 'OIL', '4T', '2T', 'SAE', 'SINTETICO', 'SEMISSINTETICO', 'MINERAL', 'GRAXA'],
        'Fluidos e aditivos' => ['FLUIDO', 'DOT 3', 'DOT 4', 'DOT3', 'DOT4', 'ADITIVO', 'ARREFECIMENTO', 'RADIADOR', 'DESENGRIPANTE', 'LIMPA'],
        'Filtros' => ['FILTRO', 'FILTER'],
        'Freios' => ['PASTILHA', 'PASTILHAS', 'LONA', 'LONAS', 'FREIO', 'SAPATA', 'BRAKE'],
        'Transmissão' => ['RELACAO', 'CORRENTE', 'COROA', 'PINHAO', 'TRANSMISSAO', 'CHAIN'],
        'Pneus' => ['PNEU', 'TIRE', 'TYRE'],
        'Câmaras de ar' => ['CAMARA', 'INNER TUBE'],
        'Velas de ignição' => ['VELA', 'SPARK PLUG', 'IGNICAO'],
        'Baterias' => ['BATERIA', 'BATTERY'],
        'Elétrica e iluminação' => ['LAMPADA', 'FAROL', 'LED', 'RELE', 'FUSIVEL', 'PISCA', 'REGULADOR', 'RETIFICADOR', 'ESTATOR', 'BOBINA', 'CHICOTE'],
        'Motor' => ['PISTAO', 'ANEL', 'ANEIS', 'JUNTA', 'CILINDRO', 'BIELA', 'VALVULA', 'RETENTOR', 'EMBREAGEM'],
        'Suspensão' => ['AMORTECEDOR', 'BENGALA', 'SUSPENSAO', 'MOLA'],
        'Cabos' => ['CABO', 'CABOS'],
        'Rolamentos' => ['ROLAMENTO', 'ROLAMENTOS', 'BEARING'],
        'Carenagem e plásticos' => ['CARENAGEM', 'PARALAMA', 'TAMPA LATERAL'],
        'Parafusos e fixadores' => ['PARAFUSO', 'PORCA', 'ARRUELA', 'PRESILHA', 'ABRACADEIRA'],
        'Acessórios' => ['RETROVISOR', 'MANOPLA', 'BAU', 'PROTETOR', 'SLIDER', 'BAGAGEIRO'],
    ];

    // Especificações que valem estar no nome: viscosidade (10W40, 10W-40) e volume (1L, 500ML).
    private const VISCOSIDADE = '/\b\d{1,2}W[- ]?\d{2}\b/';
    private const VOLUME = '/\b\d+(?:[.,]\d+)?\s?(?:ML|LT|L)\b/';

    /**
     * @param  array<int, array{texto: string, altura?: float|int, confianca?: float|int}>  $linhas
     * @return array{nome: ?string, marca: ?string, categoria: ?string, palavras: string[]}
     */
    public function interpretar(array $linhas): array
    {
        // Só o que foi lido com alguma confiança e tem letra; maiores primeiro (nome e marca costumam ter letra grande).
        $linhas = collect($linhas)
            ->map(fn ($l) => [
                'texto' => $this->limpar((string) ($l['texto'] ?? '')),
                'altura' => (float) ($l['altura'] ?? 0),
                'confianca' => (float) ($l['confianca'] ?? 100),
            ])
            // Pelo menos 2 letras/números: "5100 4T", "10W-40" e "N-1042" importam (código de peça, viscosidade).
            ->filter(fn ($l) => $l['confianca'] >= 50 && preg_match_all('/[\p{L}\p{N}]/u', $l['texto']) >= 2)
            ->sortByDesc('altura')
            ->values();

        $tudo = ' '.$linhas->map(fn ($l) => $this->normalizar($l['texto']))->implode(' ').' ';
        // Texto original em maiúsculas (sem acento), para achar "10W-40" e "1,5L" sem a limpeza estragar.
        $bruto = ' '.$linhas->map(fn ($l) => strtoupper(Str::ascii($l['texto'])))->implode(' | ').' ';

        $marca = $this->marca($linhas);
        $categoria = $this->categoria($tudo);

        return [
            'nome' => $this->nome($linhas, $marca, $categoria, $tudo, $bruto),
            'marca' => $marca,
            'categoria' => $categoria,
            // Linhas lidas (maiores primeiro) para a pessoa tocar e montar o nome.
            'palavras' => $linhas->pluck('texto')->filter(fn ($t) => mb_strlen($t) <= 60)->unique()->take(12)->values()->all(),
        ];
    }

    /** "Óleo-Motul  10w40!" → "OLEO MOTUL 10W40" (para comparar sem acento, maiúsculas e pontuação). */
    private function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii($texto)))));
    }

    /** Tira lixo comum do OCR (símbolos soltos) e espaços repetidos. */
    private function limpar(string $texto): string
    {
        $texto = preg_replace('/[^\p{L}\p{N}\s.,\-\/+%()]/u', ' ', $texto);

        return trim(preg_replace('/\s+/', ' ', $texto), " .,-/");
    }

    private function contem(string $tudoNormalizado, string $termo): bool
    {
        $termo = $this->normalizar($termo);

        return $termo !== '' && str_contains($tudoNormalizado, " {$termo} ");
    }

    /** Marca conhecida que aparece na maior linha. Prefere a grafia já cadastrada no sistema. */
    private function marca($linhas): ?string
    {
        $conhecidas = Produto::whereNotNull('marca')->distinct()->pluck('marca')
            ->merge(self::MARCAS_COMUNS)
            ->unique(fn ($m) => $this->normalizar($m))
            ->sortByDesc(fn ($m) => mb_strlen($m)) // "Pro Honda" antes de "Honda"
            ->values();

        foreach ($linhas as $linha) {
            $texto = ' '.$this->normalizar($linha['texto']).' ';
            foreach ($conhecidas as $marca) {
                if ($this->contem($texto, $marca)) {
                    return $marca;
                }
            }
        }

        return null;
    }

    /** Categoria com mais palavras-chave no texto (viscosidade tipo 10W40 conta como óleo). */
    private function categoria(string $tudo): ?string
    {
        $pontos = collect(self::PALAVRAS)->map(fn ($palavras) => collect($palavras)->filter(fn ($p) => $this->contem($tudo, $p))->count());

        if (preg_match(self::VISCOSIDADE, $tudo)) { // no texto normalizado "10W-40" vira "10W 40": o padrão aceita os dois
            $pontos['Óleos e lubrificantes'] += 2;
        }

        $melhor = $pontos->filter()->sortDesc()->keys()->first();

        // Só devolve se a categoria existe (e está ativa) no sistema.
        return $melhor && Categoria::produtoPorNome($melhor) ? $melhor : null;
    }

    /**
     * Nome sugerido: [tipo] [marca] + as 2 linhas de letra maior + especificações (10W40, 1L).
     * Ex.: "Óleo Motul 5100 4T 10W-40 1L". Tipo = a palavra da embalagem que definiu a categoria ("Óleo", "Pastilha").
     */
    private function nome($linhas, ?string $marca, ?string $categoria, string $tudo, string $bruto): ?string
    {
        $partes = $linhas
            ->reject(fn ($l) => $marca && $this->normalizar($l['texto']) === $this->normalizar($marca))
            ->take(2)
            ->pluck('texto')
            ->all();

        $nome = implode(' ', $partes);
        $tipo = $this->palavraTipo($linhas, $categoria, $tudo);

        if ($tipo && ! $this->contem(' '.$this->normalizar($nome).' ', $tipo)) {
            $temMarca = $marca && ! $this->contem(' '.$this->normalizar($nome).' ', $marca);
            $nome = trim($tipo.' '.($temMarca ? $marca : '').' '.$nome);
        }

        foreach ([self::VISCOSIDADE, self::VOLUME] as $padrao) {
            if (preg_match($padrao, $bruto, $m) && ! preg_match($padrao, ' '.strtoupper(Str::ascii($nome)).' ')) {
                $nome .= ' '.preg_replace('/[\s-]+/', '', $m[0]); // "10W-40" → "10W40", "1 L" → "1L"
            }
        }

        if ($marca && ! $this->contem(' '.$this->normalizar($nome).' ', $marca)) {
            $nome .= ' '.$marca;
        }

        $nome = $this->capitalizar(trim($nome));

        return $nome !== '' ? Str::limit($nome, 120, '') : null;
    }

    /** A palavra que definiu a categoria, escrita como está na embalagem ("Óleo" com acento, se o OCR leu assim). */
    private function palavraTipo($linhas, ?string $categoria, string $tudo): ?string
    {
        if (! $categoria) {
            return null;
        }

        foreach (self::PALAVRAS[$categoria] ?? [] as $chave) {
            if (str_contains($chave, ' ') || preg_match('/\d/', $chave) || ! $this->contem($tudo, $chave)) {
                continue; // só palavras simples ("OLEO", "PASTILHA"); "4T" e "MOTOR OIL" não servem de tipo
            }
            foreach ($linhas as $linha) {
                foreach (preg_split('/\s+/', $linha['texto']) as $palavra) {
                    if ($this->normalizar($palavra) === $chave) {
                        return $palavra;
                    }
                }
            }
        }

        return null;
    }

    /** "OLEO PARA MOTOR 10W40" → "Oleo para Motor 10W40" (palavra com número fica como está). */
    private function capitalizar(string $nome): string
    {
        $pequenas = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'PARA', 'COM', 'E', 'EM'];

        return collect(explode(' ', $nome))->map(function ($p, $i) use ($pequenas) {
            if (preg_match('/\d/', $p) || mb_strlen($p) <= 3 && mb_strtoupper($p) === $p && ! in_array(mb_strtoupper($p), $pequenas, true)) {
                return $p; // 10W40, 1L, NGK, CG
            }
            if ($i > 0 && in_array(mb_strtoupper($p), $pequenas, true)) {
                return mb_strtolower($p);
            }

            return mb_convert_case(mb_strtolower($p), MB_CASE_TITLE);
        })->implode(' ');
    }
}
