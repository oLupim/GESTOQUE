<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\Venda;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RelatorioController extends Controller
{
    public function index(Request $request)
    {
        $dados = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ], ['ate.after_or_equal' => 'A data final deve ser depois da inicial.']);

        $de = Carbon::parse($dados['de'] ?? now()->startOfMonth())->startOfDay();
        $ate = Carbon::parse($dados['ate'] ?? now())->endOfDay();
        $periodo = [$de, $ate];

        // Vendas confirmadas do período (canceladas ficam de fora).
        $vendas = fn () => DB::table('vendas')
            ->where('status', Venda::CONFIRMADA)
            ->whereBetween('created_at', $periodo);

        $itens = fn () => DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.status', Venda::CONFIRMADA)
            ->whereBetween('vendas.created_at', $periodo);

        $faturamento = (float) $vendas()->sum('total');
        $qtdVendas = $vendas()->count();
        $custo = (float) $itens()->sum(DB::raw('venda_itens.quantidade * venda_itens.custo_unitario'));
        $lucro = $faturamento - $custo;

        // Vendas por dia, com os dias sem venda preenchidos com zero.
        $porDia = $vendas()
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as qtd, SUM(total) as total')
            ->groupBy('dia')->pluck('total', 'dia');

        $vendasPorDia = collect(CarbonPeriod::create($de->copy()->startOfDay(), $ate->copy()->startOfDay()))
            ->map(fn (Carbon $d) => [
                'dia' => $d->format('d/m'),
                'total' => (float) ($porDia[$d->toDateString()] ?? 0),
            ])->values();

        $maisVendidos = $itens()
            ->join('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
            ->groupBy('produtos.id', 'produtos.codigo', 'produtos.nome')
            ->selectRaw('produtos.codigo, produtos.nome,
                SUM(venda_itens.quantidade) as quantidade,
                SUM(venda_itens.subtotal) as receita,
                SUM(venda_itens.quantidade * venda_itens.custo_unitario) as custo')
            ->orderByDesc('receita')->limit(10)->get()
            ->map(fn ($r) => [
                'codigo' => $r->codigo,
                'nome' => $r->nome,
                'quantidade' => (float) $r->quantidade,
                'receita' => (float) $r->receita,
                'lucro' => (float) $r->receita - (float) $r->custo,
            ]);

        $formas = $vendas()
            ->selectRaw('forma_pagamento, COUNT(*) as qtd, SUM(total) as total')
            ->groupBy('forma_pagamento')->orderByDesc('total')->get()
            ->map(fn ($r) => ['forma' => $r->forma_pagamento, 'qtd' => (int) $r->qtd, 'total' => (float) $r->total]);

        // Serviços: abertos no período por status; finalizados no período por mecânico.
        $servicosStatus = DB::table('ordens_servico')->whereBetween('created_at', $periodo)
            ->selectRaw('status, COUNT(*) as qtd, SUM(total) as total')
            ->groupBy('status')->get()
            ->map(fn ($r) => [
                'status' => OrdemServico::ROTULOS[$r->status] ?? $r->status,
                'qtd' => (int) $r->qtd,
                'total' => (float) $r->total,
            ]);

        $finalizadas = fn () => DB::table('ordens_servico')
            ->where('status', OrdemServico::FINALIZADA)
            ->whereBetween('finalizada_em', $periodo);

        $mecanicos = $finalizadas()
            ->selectRaw('mecanico, COUNT(*) as qtd, SUM(valor_mao_obra) as mao_obra, SUM(total) as total')
            ->groupBy('mecanico')->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'mecanico' => $r->mecanico ?: 'Sem mecânico',
                'qtd' => (int) $r->qtd,
                'mao_obra' => (float) $r->mao_obra,
                'total' => (float) $r->total,
            ]);

        $estoque = DB::table('produtos')->where('ativo', true)
            ->selectRaw('COUNT(*) as itens,
                SUM(saldo * preco_custo) as custo,
                SUM(saldo * preco_venda) as venda,
                SUM(CASE WHEN saldo <= estoque_minimo THEN 1 ELSE 0 END) as baixo')
            ->first();

        return Inertia::render('Relatorios/Index', [
            'filtro' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
            'resumo' => [
                'faturamento' => $faturamento,
                'vendas' => $qtdVendas,
                'ticket' => $qtdVendas ? $faturamento / $qtdVendas : 0,
                'custo' => $custo,
                'lucro' => $lucro,
                'margem' => $faturamento > 0 ? $lucro / $faturamento * 100 : 0,
                'servicos' => $finalizadas()->count(),
                'servicos_total' => (float) $finalizadas()->sum('total'),
            ],
            'vendasPorDia' => $vendasPorDia,
            'maisVendidos' => $maisVendidos,
            'formas' => $formas,
            'servicosStatus' => $servicosStatus,
            'mecanicos' => $mecanicos,
            'estoque' => [
                'itens' => (int) $estoque->itens,
                'custo' => (float) $estoque->custo,
                'venda' => (float) $estoque->venda,
                'baixo' => (int) $estoque->baixo,
            ],
        ]);
    }
}