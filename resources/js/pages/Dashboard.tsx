import { useState } from "react";
import { Link } from "@inertiajs/react";
import { ShoppingCart, Package, AlertTriangle, Wrench, Plus, TrendingUp, ArrowRight, Receipt } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { StatCard, Card, Table, Th, Td, Button, StatusBadge } from "@/components/ui";

type Props = {
  nome: string;
  saudacao: string;
  cards: {
    vendas_hoje: number; qtd_hoje: number; variacao_hoje: number | null;
    faturamento_mes: number; vendas_mes: number; servicos_mes: number; ticket_medio: number;
    produtos_ativos: number; estoque_baixo: number; servicos_abertos: number; aguardando_peca: number;
  };
  grafico: { dia: string; data: string; valor: number }[];
  movimentacoes: { id: number; origem: string; produto: string; quantidade: number; hora: string }[];
  estoqueBaixo: { id: number; nome: string; codigo: string; saldo: number; estoque_minimo: number; situacao: string }[];
};

const brl = (v: number, casas = 2) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: casas }).format(v);
const qtd = (v: number) => v.toLocaleString("pt-BR", { maximumFractionDigits: 3 });

function Grafico({ dados }: { dados: Props["grafico"] }) {
  const [hover, setHover] = useState<number | null>(null);
  const max = Math.max(...dados.map((d) => d.valor), 1);

  return (
    <div className="flex items-end justify-between gap-2 h-44 px-1 pt-4">
      {dados.map((d, i) => (
        <div key={d.data} className="flex flex-col items-center gap-1.5 flex-1 h-full justify-end" onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
          <div className="text-white rounded px-2 py-1 whitespace-nowrap" style={{ background: "#171918", fontSize: 11, fontFamily: "var(--font-mono)", visibility: hover === i ? "visible" : "hidden" }}>
            {brl(d.valor, 0)}
          </div>
          <div
            className="w-full rounded-t-sm transition-all duration-200"
            style={{ height: `${(d.valor / max) * 100}%`, minHeight: 4, background: hover === i ? "#C9281F" : d.valor > 0 ? "#D8D4CC" : "#EFECE5" }}
          />
          <div className="text-gray-text" style={{ fontSize: 11 }}>{d.dia}</div>
        </div>
      ))}
    </div>
  );
}

export default function Dashboard({ nome, saudacao, cards, grafico, movimentacoes, estoqueBaixo }: Props) {
  const totalSemana = grafico.reduce((s, d) => s + d.valor, 0);
  const hoje = new Date().toLocaleDateString("pt-BR", { weekday: "long", day: "numeric", month: "long" });

  return (
    <AppLayout title="Dashboard">
      <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
          <h1 className="text-charcoal leading-tight" style={{ fontFamily: "var(--font-condensed)", fontWeight: 800, fontSize: 28 }}>
            {saudacao}, {nome.split(" ")[0]}
          </h1>
          <p className="text-gray-text mt-0.5" style={{ fontSize: 13.5 }}>Visão geral da oficina — {hoje}</p>
        </div>
        <div className="flex items-center gap-2">
          <Link href="/servicos"><Button variant="secondary"><Wrench size={14} /> Novo serviço</Button></Link>
          <Link href="/vendas/nova"><Button variant="primary"><Plus size={14} /> Nova venda</Button></Link>
        </div>
      </div>

      <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(190px, 1fr))" }}>
        <StatCard
          label="Vendas hoje"
          value={brl(cards.vendas_hoje, 0)}
          sub={`${cards.qtd_hoje} ${cards.qtd_hoje === 1 ? "venda" : "vendas"}`}
          icon={ShoppingCart}
          trend={cards.variacao_hoje !== null ? { value: `${cards.variacao_hoje > 0 ? "+" : ""}${cards.variacao_hoje}% vs ontem`, up: cards.variacao_hoje >= 0 } : undefined}
        />
        <StatCard
          label="Faturamento do mês"
          value={brl(cards.faturamento_mes, 0)}
          sub={`vendas ${brl(cards.vendas_mes, 0)} · serviços ${brl(cards.servicos_mes, 0)}`}
          icon={TrendingUp}
          variant="success"
        />
        <StatCard label="Ticket médio" value={brl(cards.ticket_medio)} sub="por venda no mês" icon={Receipt} />
        <StatCard label="Produtos ativos" value={`${cards.produtos_ativos}`} sub="cadastrados" icon={Package} />
        <StatCard label="Estoque baixo" value={`${cards.estoque_baixo}`} sub="no mínimo ou abaixo" icon={AlertTriangle} variant="warning" />
        <StatCard label="Serviços em aberto" value={`${cards.servicos_abertos}`} sub={`${cards.aguardando_peca} aguardando peça`} icon={Wrench} variant="warning" />
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
        <Card className="xl:col-span-2 p-5">
          <div className="flex items-center justify-between mb-2">
            <div>
              <div className="text-charcoal font-semibold" style={{ fontFamily: "var(--font-display)", fontSize: 14 }}>Vendas — últimos 7 dias</div>
              <div className="text-gray-text" style={{ fontSize: 12 }}>Faturamento diário de vendas de balcão</div>
            </div>
            <div className="text-charcoal font-bold" style={{ fontFamily: "var(--font-condensed)", fontSize: 20 }}>{brl(totalSemana)}</div>
          </div>
          <Grafico dados={grafico} />
        </Card>

        <Card className="p-5">
          <div className="text-charcoal font-semibold mb-4" style={{ fontFamily: "var(--font-display)", fontSize: 14 }}>Movimentações recentes</div>
          <div className="space-y-3">
            {movimentacoes.map((m) => (
              <div key={m.id} className="flex items-start gap-3">
                <div
                  className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold"
                  style={{ background: m.quantidade > 0 ? "rgba(26,124,78,0.12)" : "rgba(201,40,31,0.10)", color: m.quantidade > 0 ? "#1A7C4E" : "#C9281F" }}
                >
                  {m.quantidade > 0 ? "+" : "−"}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="text-charcoal font-medium truncate" style={{ fontSize: 12.5 }}>{m.origem}</div>
                  <div className="text-gray-text truncate" style={{ fontSize: 11.5 }}>{m.quantidade > 0 ? "+" : ""}{qtd(m.quantidade)} {m.produto}</div>
                </div>
                <div className="text-gray-text flex-shrink-0" style={{ fontSize: 10.5 }}>{m.hora}</div>
              </div>
            ))}
            {movimentacoes.length === 0 && <div className="text-gray-text text-sm">Nenhuma movimentação ainda.</div>}
          </div>
          <Link href="/estoque" className="w-full mt-4 py-2 text-red text-xs font-medium flex items-center justify-center gap-1 rounded hover:bg-red/5">
            Ver todas <ArrowRight size={12} />
          </Link>
        </Card>
      </div>

      <Card>
        <div className="flex items-center justify-between px-5 py-4" style={{ borderBottom: "1px solid #F0EDE5" }}>
          <div>
            <div className="text-charcoal font-semibold" style={{ fontFamily: "var(--font-display)", fontSize: 14 }}>Produtos com estoque baixo</div>
            <div className="text-gray-text" style={{ fontSize: 12 }}>Reposição recomendada</div>
          </div>
          <Link href="/estoque"><Button variant="secondary" size="sm">Ver estoque <ArrowRight size={12} /></Button></Link>
        </div>
        <Table>
          <thead>
            <tr><Th>Produto</Th><Th>Código</Th><Th className="text-right">Atual</Th><Th className="text-right">Mínimo</Th><Th>Status</Th></tr>
          </thead>
          <tbody>
            {estoqueBaixo.map((p) => (
              <tr key={p.id} className="hover:bg-cream/40 transition-fast">
                <Td><span className="font-medium text-charcoal">{p.nome}</span></Td>
                <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{p.codigo}</span></Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontWeight: 500, color: p.saldo <= 0 ? "#C9281F" : "#171918" }}>{qtd(p.saldo)}</span></Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{qtd(p.estoque_minimo)}</span></Td>
                <Td><StatusBadge status={p.situacao} /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {estoqueBaixo.length === 0 && <div className="py-8 text-center text-gray-text text-sm">Nenhum produto abaixo do mínimo.</div>}
      </Card>
    </AppLayout>
  );
}