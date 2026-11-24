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
        // Não entram marcas que também são palavras comuns na embalagem ("Total", "Allen", "Iridium").
        'Elf', 'Repsol', 'Texaco', 'Liqui Moly', 'Bardahl', 'Radnaq', 'Tirreno', 'Sachs', 'Nakata', 'Mahle', 'Metal Leve',
        'Magneti Marelli', 'Hipper Freios', 'Syl', 'Tork', 'Pro Tork', 'Scud', 'Mixs', 'Valflex', 'Brasfort', 'Paraflu',
        'Tecbril', 'Gauss', 'Dunlop', 'Bridgestone', 'Vipal', 'Loctite', 'Tekbond', 'Fortbras',
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
     * @return array{nome: ?string, opcoes: string[], marca: ?string, categoria: ?string, palavras: string[]}
     */
    public function interpretar(array $linhas): array
    {
        $marcas = $this->marcasConhecidas();

        // Marcas lidas com erro típico de OCR ("M0TUL", "COBRE0") são corrigidas aqui. Maiores primeiro.
        $lidas = collect($linhas)
            ->map(fn ($l) => [
                'texto' => $this->corrigirMarcas($this->limpar((string) ($l['texto'] ?? '')), $marcas),
                // Linha inteira, com as palavras de pouca certeza também (o celular manda em "completo").
                'completo' => $this->corrigirMarcas($this->limpar((string) ($l['completo'] ?? $l['texto'] ?? '')), $marcas),
                'altura' => (float) ($l['altura'] ?? 0),
                'confianca' => (float) ($l['confianca'] ?? 100),
            ])
            ->sortByDesc('altura')
            ->values();

        // Para achar marca e categoria vale quase tudo o que foi lido: foto de celular sai meio borrada
        // e a marca pode vir com pouca certeza. Errar aqui custa pouco (a pessoa confere).
        $pistas = $lidas->filter(fn ($l) => $l['confianca'] >= 20 && preg_match_all('/\p{L}/u', $l['completo']) >= 2)
            ->map(fn ($l) => [...$l, 'texto' => $l['completo']])->values();

        // Para o nome, só o que foi lido com mais certeza. Pelo menos 2 letras/números:
        // "5100 4T", "10W-40" e "N-1042" importam (código de peça, viscosidade).
        $linhas = $lidas->filter(fn ($l) => $l['confianca'] >= 50 && preg_match_all('/[\p{L}\p{N}]/u', $l['texto']) >= 2)->values();

        $categoria = $this->categoria($this->textoNormalizado($pistas));

        // Óleo de moto: "4T"/"2T" (tempos do motor) é lido muito como "41"/"4l". Só corrige em óleo, onde é certo.
        if ($categoria === 'Óleos e lubrificantes') {
            $linhas = $linhas->map(fn ($l) => [...$l, 'texto' => preg_replace('/\b([24])[1lI]\b/', '$1T', $l['texto'])]);
        }
        $tudo = $this->textoNormalizado($linhas);

        // Texto original em maiúsculas (sem acento), para achar "10W-40" e "1,5L" sem a limpeza estragar.
        $bruto = ' '.$linhas->map(fn ($l) => strtoupper(Str::ascii($l['texto'])))->implode(' | ').' ';

        $marca = $this->marca($pistas, $marcas);
        $opcoes = $this->opcoesDeNome($linhas, $marca, $categoria, $tudo, $bruto);

        return [
            'nome' => $opcoes[0] ?? null,
            // Até 3 nomes possíveis, com o texto como está na embalagem: a pessoa escolhe com um toque.
            'opcoes' => $opcoes,
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

    /** Todas as linhas normalizadas, com espaço nas pontas (para o `contem`). */
    private function textoNormalizado($linhas): string
    {
        return ' '.$linhas->map(fn ($l) => $this->normalizar($l['texto']))->implode(' ').' ';
    }

    /**
     * Palavra-chave de categoria no texto, aceitando o que a foto real costuma trazer:
     * plural ("OLEOS", "FILTROS", "LUBRIFICANTES"), número no lugar de letra ("0LEO", "F1LTRO")
     * e uma letra errada em palavra comprida ("PASTILNA", "LUBRIF1CANTE").
     */
    private function temPalavraChave(string $tudo, string $chave): bool
    {
        if ($this->contem($tudo, $chave)) {
            return true;
        }
        $chave = $this->normalizar($chave);
        if (str_contains($chave, ' ') || preg_match('/\d/', $chave)) {
            return false; // "MOTOR OIL", "4T", "DOT 4": só exatos
        }
        foreach (array_unique(explode(' ', trim($tudo))) as $palavra) {
            if (preg_match_all('/[A-Z]/', $palavra) < 2) {
                continue; // números ("5100", "10W40") não viram letras
            }
            $palavra = strtr($palavra, ['0' => 'O', '1' => 'I', '5' => 'S', '8' => 'B']);
            if ($palavra === $chave || $palavra === $chave.'S' || $palavra === $chave.'ES') {
                return true;
            }
            if (strlen($chave) >= 6 && abs(strlen($palavra) - strlen($chave)) <= 1 && levenshtein($palavra, $chave) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Marcas já cadastradas (grafia do sistema primeiro) + marcas comuns; as mais longas antes ("Pro Honda" antes de "Honda"). */
    private function marcasConhecidas()
    {
        return Produto::whereNotNull('marca')->distinct()->pluck('marca')
            ->merge(self::MARCAS_COMUNS)
            ->unique(fn ($m) => $this->normalizar($m))
            ->sortByDesc(fn ($m) => mb_strlen($m))
            ->values();
    }

    /**
     * Corrige marca lida com erro de OCR, palavra por palavra (só palavras com 4+ caracteres e 2+ letras):
     * 1) troca de número por letra parecida: "M0TUL" → "MOTUL", "C0BREQ" → "COBREQ";
     * 2) uma letra errada em marca de 5+ letras: "MOTUI" → "MOTUL".
     * Mantém o estilo: palavra toda em maiúsculas continua em maiúsculas.
     */
    private function corrigirMarcas(string $texto, $marcas): string
    {
        // Só marcas de uma palavra; comparadas sem acento/pontuação ("FRAS-LE" → "FRASLE").
        $alvos = $marcas->mapWithKeys(fn ($m) => [str_replace(' ', '', $this->normalizar($m)) => $m])
            ->filter(fn ($m, $chave) => strlen($chave) >= 4 && ! str_contains(trim($m), ' '));

        return preg_replace_callback('/[\p{L}\p{N}]{4,}/u', function ($m) use ($alvos) {
            $palavra = $m[0];
            if (preg_match_all('/\p{L}/u', $palavra) < 2) {
                return $palavra; // "5100", "1042": números ficam como estão
            }

            $norm = str_replace(' ', '', $this->normalizar($palavra));
            if ($alvos->has($norm)) {
                return $palavra; // já está certa
            }

            $semConfusao = strtr($norm, ['0' => 'O', '1' => 'I', '5' => 'S', '8' => 'B']);
            $achada = $alvos->get($semConfusao)
                ?? $alvos->first(fn ($marca, $chave) => strlen($chave) >= 5 && strlen($chave) === strlen($norm)
                    && $chave[0] === $norm[0] && levenshtein($chave, $norm) === 1);

            if (! $achada) {
                return $palavra;
            }

            return mb_strtoupper($palavra) === $palavra ? mb_strtoupper($achada) : $achada;
        }, $texto);
    }

    /** Marca conhecida que aparece na maior linha. Prefere a grafia já cadastrada no sistema. */
    private function marca($linhas, $conhecidas): ?string
    {
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
        $pontos = collect(self::PALAVRAS)->map(fn ($palavras) => collect($palavras)->filter(fn ($p) => $this->temPalavraChave($tudo, $p))->count());

        if (preg_match(self::VISCOSIDADE, $tudo)) { // no texto normalizado "10W-40" vira "10W 40": o padrão aceita os dois
            $pontos['Óleos e lubrificantes'] += 2;
        }

        $melhor = $pontos->filter()->sortDesc()->keys()->first();

        // Só devolve se a categoria existe (e está ativa) no sistema.
        return $melhor && Categoria::produtoPorNome($melhor) ? $melhor : null;
    }

    /**
     * Até 3 nomes possíveis, sempre com as palavras como estão escritas na embalagem (sem mudar maiúsculas):
     * 1) completo: [tipo] [marca] + 2 linhas de letra maior + especificações que faltarem ("Óleo MOTUL 5100 4T 10W-40 1L");
     * 2) as 2 linhas de letra maior, como estão ("MOTUL 5100 4T");
     * 3) só a maior linha que não é a marca ("5100 4T").
     *
     * @return string[]
     */
    private function opcoesDeNome($linhas, ?string $marca, ?string $categoria, string $tudo, string $bruto): array
    {
        $ehMarca = fn ($l) => $marca && $this->normalizar($l['texto']) === $this->normalizar($marca);
        $semMarca = $linhas->reject($ehMarca)->pluck('texto');
        $marcaEscrita = $marca ? $this->comoEscrito($linhas, $marca) : null;

        // 1) Completo.
        $completo = $semMarca->take(2)->implode(' ');
        $tipo = $this->palavraTipo($linhas, $categoria, $tudo);
        // Por referência: olha o nome como ele está no momento (vai crescendo nos passos abaixo).
        $temNoNome = function (string $termo) use (&$completo) {
            return $this->contem(' '.$this->normalizar($completo).' ', $termo);
        };

        if ($tipo && ! $temNoNome($tipo)) {
            $completo = trim($tipo.' '.($marca && ! $temNoNome($marca) ? $marcaEscrita : '').' '.$completo);
        }
        foreach ([self::VISCOSIDADE, self::VOLUME] as $padrao) {
            if (preg_match($padrao, $bruto, $m) && ! preg_match($padrao, ' '.strtoupper(Str::ascii($completo)).' ')) {
                $completo .= ' '.trim($m[0]); // como está escrito: "10W-40", "1L"
            }
        }
        if ($marca && ! $temNoNome($marca)) {
            $completo .= ' '.$marcaEscrita;
        }

        $opcoes = [
            $completo,
            $linhas->take(2)->pluck('texto')->implode(' '), // 2) duas maiores, como estão
            (string) $semMarca->first(),                       // 3) maior linha sem a marca
        ];

        return collect($opcoes)
            ->map(fn ($o) => Str::limit(trim(preg_replace('/\s+/', ' ', $o)), 120, ''))
            ->filter(fn ($o) => preg_match('/\p{L}/u', $o) && preg_match_all('/[\p{L}\p{N}]/u', $o) >= 3) // "5100 4T" vale
            ->unique(fn ($o) => $this->normalizar($o))
            ->values()
            ->all();
    }

    /** A marca como aparece no texto lido ("MOTUL" se estava em maiúsculas); se não achar, a grafia conhecida. */
    private function comoEscrito($linhas, string $termo): string
    {
        foreach ($linhas as $linha) {
            foreach (preg_split('/\s+/', $linha['texto']) as $palavra) {
                if ($this->normalizar($palavra) === $this->normalizar($termo)) {
                    return $palavra;
                }
            }
        }

        return $termo;
    }

    /** A palavra que definiu a categoria, escrita como está na embalagem ("Óleo" com acento, se o OCR leu assim). */
    private function palavraTipo($linhas, ?string $categoria, string $tudo): ?string
    {
        if (! $categoria) {
            return null;
        }

        foreach (self::PALAVRAS[$categoria] ?? [] as $chave) {
            if (str_contains($chave, ' ') || preg_match('/\d/', $chave) || ! $this->temPalavraChave($tudo, $chave)) {
                continue; // só palavras simples ("OLEO", "PASTILHA"); "4T" e "MOTOR OIL" não servem de tipo
            }
            foreach ($linhas as $linha) {
                foreach (preg_split('/\s+/', $linha['texto']) as $palavra) {
                    if ($this->temPalavraChave(' '.$this->normalizar($palavra).' ', $chave)) {
                        return $palavra;
                    }
                }
            }
        }

        return null;
    }
}
