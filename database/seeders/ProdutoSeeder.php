<?php

namespace Database\Seeders;

use App\Enums\TipoMovimentacao;
use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Database\Seeder;

class ProdutoSeeder extends Seeder
{
    public function run(EstoqueService $estoque): void
    {
        // [codigo, nome, categoria, marca, unidade, custo, venda, saldo, minimo, ncm]
        $produtos = [
            ['OLE-001', 'Óleo Motul 10W40', 'Lubrificantes', 'Motul', 'UN', 42.00, 65.00, 12, 5, '27101932'],
            ['PAS-024', 'Pastilha de freio dianteira', 'Freios', 'Cobreq', 'PAR', 28.00, 52.00, 3, 5, '68138100'],
            ['FIL-013', 'Filtro de óleo', 'Filtros', 'Fram', 'UN', 14.00, 28.00, 4, 6, '84212300'],
            ['REL-042', 'Relação completa CG 160', 'Transmissão', 'DID', 'KIT', 185.00, 320.00, 2, 2, '73151100'],
            ['VEL-007', 'Vela de ignição NGK', 'Ignição', 'NGK', 'UN', 12.00, 22.00, 18, 8, '85111000'],
            ['FLU-003', 'Fluido de freio DOT4', 'Lubrificantes', 'Bosch', 'FR', 18.00, 32.00, 6, 4, '38190000'],
            ['COR-011', 'Corrente 428H x 120L', 'Transmissão', 'DID', 'UN', 68.00, 120.00, 5, 3, '73151100'],
            ['ROL-029', 'Rolamento de roda dianteiro', 'Rolamentos', 'SKF', 'UN', 32.00, 58.00, 7, 4, '84821010'],
            ['PAS-025', 'Pastilha de freio traseira', 'Freios', 'Cobreq', 'PAR', 25.00, 46.00, 2, 5, '68138100'],
            ['CAM-018', 'Câmara de ar 2.75-18', 'Pneus', 'Pirelli', 'UN', 22.00, 39.00, 0, 2, '40139000'],
        ];

        foreach ($produtos as [$codigo, $nome, $cat, $marca, $un, $custo, $venda, $saldo, $min, $ncm]) {
            $p = Produto::create([
                'codigo' => $codigo, 'nome' => $nome, 'categoria' => $cat, 'marca' => $marca,
                'unidade' => $un, 'preco_custo' => $custo, 'preco_venda' => $venda,
                'estoque_minimo' => $min, 'ncm' => $ncm, 'cfop' => '5102',
            ]);

            if ($saldo > 0) {
                $estoque->entrar($p, $saldo, TipoMovimentacao::EstoqueInicial, motivo: 'Implantação do sistema');
            }
        }
    }
}