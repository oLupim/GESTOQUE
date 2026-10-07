<?php

namespace App\Http\Controllers;

use App\Models\Movimentacao;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    public function index(Request $request): Response
    {
        $vendas = fn () => Venda::where('status', Venda::CONFIRMADA);

        $hoje = (float) $vendas()->whereDate('created_at', today())->sum('total');
        $qtdHoje = $vendas()->whereDate('created_at', today())->count();
        $ontem = (float) $vendas()->whereDate('created_at', today()->subDay())->sum('total');

        $inicioMes = now()->startOfMonth();
        $vendasMes = (float) $vendas()->where('created_at', '>=', $inicioMes)->sum('total');
        $qtdMes = $vendas()->where('created_at', '>=', $inicioMes)->count();
        $servicosMes = (float) OrdemServico::where('status', OrdemServico::FINALIZADA)->where('finalizada_em', '>=', $inicioMes)->sum('total');

        $abertos = OrdemServico::whereIn('status', [OrdemServico::ABERTA, OrdemServico::EM_ANDAMENTO, OrdemServico::AGUARDANDO_PECA]);

        // Últimos 7 dias, inclusive os sem venda (agrupado em PHP: funciona igual no SQLite e no PostgreSQL).
        $porDia = $vendas()->where('created_at', '>=', today()->subDays(6))->get(['total', 'created_at'])
            ->groupBy(fn (Venda $v) => $v->created_at->toDateString())
            ->map(fn ($grupo) => round($grupo->sum(fn (Venda $v) => (float) $v->total), 2));

        $grafico = collect(range(6, 0))->map(function (int $atras) use ($porDia) {
            $dia = today()->subDays($atras);

            return ['dia' => self::DIAS[$dia->dayOfWeek], 'data' => $dia->format('d/m'), 'valor' => $porDia[$dia->toDateString()] ?? 0];
        });

        return Inertia::render('Dashboard', [
            'nome' => $request->user()->name,
            'saudacao' => match (true) {
                now()->hour < 12 => 'Bom dia',
                now()->hour < 18 => 'Boa tarde',
                default => 'Boa noite',
            },
            'cards' => [
                'vendas_hoje' => $hoje,
                'qtd_hoje' => $qtdHoje,
                'variacao_hoje' => $ontem > 0 ? round(($hoje - $ontem) / $ontem * 100) : null,
                'faturamento_mes' => round($vendasMes + $servicosMes, 2),
                'vendas_mes' => $vendasMes,
                'servicos_mes' => $servicosMes,
                'ticket_medio' => $qtdMes > 0 ? round($vendasMes / $qtdMes, 2) : 0,
                'produtos_ativos' => Produto::where('ativo', true)->count(),
                'estoque_baixo' => Produto::where('ativo', true)->whereColumn('saldo', '<=', 'estoque_minimo')->count(),
                'servicos_abertos' => (clone $abertos)->count(),
                'aguardando_peca' => (clone $abertos)->where('status', OrdemServico::AGUARDANDO_PECA)->count(),
            ],
            'grafico' => $grafico,
            'movimentacoes' => Movimentacao::with('produto:id,nome')->latest('id')->limit(6)->get()->map(fn (Movimentacao $m) => [
                'id' => $m->id,
                'origem' => $m->descricaoOrigem(),
                'produto' => $m->produto->nome,
                'quantidade' => (float) $m->quantidade,
                'hora' => $m->created_at->isToday() ? $m->created_at->format('H:i') : $m->created_at->format('d/m'),
            ]),
            'estoqueBaixo' => Produto::where('ativo', true)->whereColumn('saldo', '<=', 'estoque_minimo')
                ->orderByRaw('saldo - estoque_minimo')->limit(5)->get()
                ->map(fn (Produto $p) => [
                    'id' => $p->id, 'nome' => $p->nome, 'codigo' => $p->codigo,
                    'saldo' => (float) $p->saldo, 'estoque_minimo' => (float) $p->estoque_minimo, 'situacao' => $p->situacaoEstoque(),
                ]),
        ]);
    }
}