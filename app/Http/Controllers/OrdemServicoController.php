<?php

namespace App\Http\Controllers;

use App\Exceptions\EstoqueInsuficienteException;
use App\Http\Requests\StoreOrdemServicoRequest;
use App\Models\Cliente;
use App\Models\Motocicleta;
use App\Models\OrdemServico;
use App\Models\OsItem;
use App\Models\Produto;
use App\Services\OrdemServicoService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrdemServicoController extends Controller
{
    public function __construct(private OrdemServicoService $servico) {}

    public function index(): Response
    {
        $ordens = OrdemServico::with(['cliente:id,nome', 'motocicleta:id,placa,marca,modelo'])->latest('id')->limit(200)->get();

        return Inertia::render('Servicos/Index', [
            'ordens' => $ordens->map(fn (OrdemServico $os) => [
                'id' => $os->id,
                'data' => $os->created_at->format('d/m/Y'),
                'cliente' => $os->cliente->nome,
                'moto' => "{$os->motocicleta->marca} {$os->motocicleta->modelo}",
                'placa' => $os->motocicleta->placa,
                'servico' => $os->descricao_servico ?? $os->problema ?? '—',
                'mecanico' => $os->mecanico ?? '—',
                'total' => (float) $os->total,
                'status' => $os->rotulo(),
            ]),
            'contagem' => collect(OrdemServico::ROTULOS)->map(fn ($r, $s) => $ordens->where('status', $s)->count()),
            'clientes' => Cliente::with('motocicletas:id,cliente_id,placa,marca,modelo,km_atual')->orderBy('nome')->get(['id', 'nome'])
                ->map(fn (Cliente $c) => ['id' => $c->id, 'nome' => $c->nome, 'motos' => $c->motocicletas->map(fn (Motocicleta $m) => [
                    'id' => $m->id, 'descricao' => "{$m->marca} {$m->modelo} · {$m->placa}", 'km_atual' => $m->km_atual,
                ])]),
            'produtos' => $this->produtosDisponiveis(),
        ]);
    }

    public function show(OrdemServico $os): Response
    {
        $os->load(['cliente', 'motocicleta', 'itens.produto:id,nome,codigo,unidade', 'usuario:id,name']);

        return Inertia::render('Servicos/Show', [
            'os' => [
                'id' => $os->id,
                'status' => $os->status,
                'rotulo' => $os->rotulo(),
                'editavel' => $os->editavel(),
                'transicoes' => collect(OrdemServico::TRANSICOES[$os->status])->map(fn ($s) => ['valor' => $s, 'rotulo' => OrdemServico::ROTULOS[$s]])->values(),
                'aberta_em' => $os->created_at->format('d/m/Y H:i'),
                'finalizada_em' => $os->finalizada_em?->format('d/m/Y H:i'),
                'motivo_cancelamento' => $os->motivo_cancelamento,
                'cliente' => ['nome' => $os->cliente->nome, 'telefone' => $os->cliente->telefone],
                'moto' => ['descricao' => "{$os->motocicleta->marca} {$os->motocicleta->modelo}", 'placa' => $os->motocicleta->placa, 'km_atual' => $os->motocicleta->km_atual],
                'km_entrada' => $os->km_entrada,
                'mecanico' => $os->mecanico,
                'problema' => $os->problema,
                'descricao_servico' => $os->descricao_servico,
                'valor_mao_obra' => (float) $os->valor_mao_obra,
                'valor_pecas' => (float) $os->valor_pecas,
                'total' => (float) $os->total,
                'itens' => $os->itens->map(fn (OsItem $i) => [
                    'id' => $i->id, 'produto' => $i->produto->nome, 'codigo' => $i->produto->codigo, 'unidade' => $i->produto->unidade,
                    'quantidade' => (float) $i->quantidade, 'preco_unitario' => (float) $i->preco_unitario, 'subtotal' => (float) $i->subtotal,
                ]),
            ],
            'produtos' => $this->produtosDisponiveis(),
        ]);
    }

    public function store(StoreOrdemServicoRequest $request): RedirectResponse
    {
        try {
            $os = $this->servico->abrir($request->validated(), $request->user());
        } catch (EstoqueInsuficienteException $e) {
            return back()->withErrors(['pecas' => $e->getMessage()]);
        }

        return redirect()->route('servicos.show', $os)->with('success', "OS #{$os->id} aberta.");
    }

    public function update(Request $request, OrdemServico $os): RedirectResponse
    {
        $request->merge(['valor_mao_obra' => str_replace(',', '.', (string) $request->input('valor_mao_obra', '0'))]);
        $dados = $request->validate([
            'mecanico' => ['nullable', 'string', 'max:60'],
            'problema' => ['nullable', 'string', 'max:2000'],
            'descricao_servico' => ['nullable', 'string', 'max:200'],
            'valor_mao_obra' => ['required', 'numeric', 'min:0'],
        ], ['valor_mao_obra.numeric' => 'Valor inválido.']);

        return $this->executar(fn () => $this->servico->atualizar($os, $dados), 'Serviço atualizado.');
    }

    public function status(Request $request, OrdemServico $os): RedirectResponse
    {
        $dados = $request->validate(['status' => ['required', Rule::in(array_keys(OrdemServico::ROTULOS))]]);

        return $this->executar(fn () => $this->servico->alterarStatus($os, $dados['status']), 'Status atualizado.');
    }

    public function cancelar(Request $request, OrdemServico $os): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']], ['motivo.required' => 'Informe o motivo.']);

        return $this->executar(fn () => $this->servico->cancelar($os, $dados['motivo'], $request->user()), "OS #{$os->id} cancelada. Peças devolvidas ao estoque.");
    }

    public function adicionarPeca(Request $request, OrdemServico $os): RedirectResponse
    {
        $request->merge(['quantidade' => str_replace(',', '.', (string) $request->input('quantidade'))]);
        $dados = $request->validate([
            'produto_id' => ['required', 'exists:produtos,id'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
        ], ['produto_id.required' => 'Escolha a peça.', 'quantidade.gt' => 'Quantidade deve ser maior que zero.']);

        try {
            $this->servico->adicionarPeca($os, (int) $dados['produto_id'], $dados['quantidade'], $request->user());
        } catch (EstoqueInsuficienteException $e) {
            return back()->withErrors(['quantidade' => $e->getMessage()]);
        } catch (DomainException $e) {
            return back()->withErrors(['os' => $e->getMessage()]);
        }

        return back()->with('success', 'Peça adicionada e baixada do estoque.');
    }

    public function removerPeca(Request $request, OrdemServico $os, OsItem $item): RedirectResponse
    {
        abort_unless($item->ordem_servico_id === $os->id, 404);

        return $this->executar(fn () => $this->servico->removerPeca($item, $request->user()), 'Peça removida e devolvida ao estoque.');
    }

    private function executar(callable $acao, string $sucesso): RedirectResponse
    {
        try {
            $acao();
        } catch (DomainException $e) {
            return back()->withErrors(['os' => $e->getMessage()]);
        }

        return back()->with('success', $sucesso);
    }

    private function produtosDisponiveis()
    {
        return Produto::where('ativo', true)->where('saldo', '>', 0)->orderBy('nome')->get()->map(fn (Produto $p) => [
            'id' => $p->id, 'nome' => $p->nome, 'codigo' => $p->codigo, 'unidade' => $p->unidade,
            'saldo' => (float) $p->saldo, 'preco_venda' => (float) $p->preco_venda,
        ]);
    }
}