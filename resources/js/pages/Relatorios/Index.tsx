import { Head, router } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';
import AppLayout from '@/layouts/AppLayout';

type Props = {
  filtro: { de: string; ate: string };
  resumo: {
    faturamento: number; vendas: number; ticket: number; custo: number;
    lucro: number; margem: number; servicos: number; servicos_total: number;
  };
  vendasPorDia: { dia: string; total: number }[];
  maisVendidos: { codigo: string; nome: string; quantidade: number; receita: number; lucro: number }[];
  formas: { forma: string; qtd: number; total: number }[];
  servicosStatus: { status: string; qtd: number; total: number }[];
  mecanicos: { mecanico: string; qtd: number; mao_obra: number; total: number }[];
  estoque: { itens: number; custo: number; venda: number; baixo: number };
};

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const num = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 3 });
const FORMAS: Record<string, string> = { pix: 'PIX', dinheiro: 'Dinheiro', debito: 'Débito', credito: 'Crédito' };
const campo = 'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm';

function Card({ titulo, valor, detalhe }: { titulo: string; valor: string; detalhe?: string }) {
  return (
    <div className="rounded-lg border border-neutral-200 bg-white p-4">
      <div className="text-xs font-semibold uppercase text-neutral-500">{titulo}</div>
      <div className="mt-1 text-2xl font-bold">{valor}</div>
      {detalhe && <div className="text-xs text-neutral-500">{detalhe}</div>}
    </div>
  );
}

function Bloco({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="rounded-lg border border-neutral-200 bg-white p-5">
      <h2 className="mb-4 font-semibold">{titulo}</h2>
      {children}
    </section>
  );
}

function Vazio() {
  return <p className="py-6 text-center text-sm text-neutral-500">Sem dados no período.</p>;
}

export default function Relatorios(p: Props) {
  const [de, setDe] = useState(p.filtro.de);
  const [ate, setAte] = useState(p.filtro.ate);
  const maxDia = Math.max(1, ...p.vendasPorDia.map((d) => d.total));

  const filtrar = (e: FormEvent) => {
    e.preventDefault();
    router.get('/relatorios', { de, ate }, { preserveState: true });
  };

  const atalho = (dias: number | 'mes') => {
    const hoje = new Date();
    const ini = dias === 'mes' ? new Date(hoje.getFullYear(), hoje.getMonth(), 1) : new Date(hoje.getTime() - (dias - 1) * 864e5);
    const f = (d: Date) => d.toLocaleDateString('sv-SE'); // AAAA-MM-DD
    router.get('/relatorios', { de: f(ini), ate: f(hoje) });
  };

  return (
    <AppLayout title="Relatórios">
      <Head title="Relatórios" />
      <div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <h1 className="text-2xl font-bold">Relatórios</h1>
          <form onSubmit={filtrar} className="flex flex-wrap items-end gap-2">
            <button type="button" onClick={() => atalho(7)} className={campo}>7 dias</button>
            <button type="button" onClick={() => atalho(30)} className={campo}>30 dias</button>
            <button type="button" onClick={() => atalho('mes')} className={campo}>Este mês</button>
            <input type="date" className={campo} value={de} onChange={(e) => setDe(e.target.value)} />
            <input type="date" className={campo} value={ate} onChange={(e) => setAte(e.target.value)} />
            <button className="rounded-md bg-[#C9281F] px-4 py-2 text-sm font-semibold text-white">Filtrar</button>
          </form>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card titulo="Faturamento (vendas)" valor={brl(p.resumo.faturamento)} detalhe={`${p.resumo.vendas} vendas · ticket ${brl(p.resumo.ticket)}`} />
          <Card titulo="Lucro bruto" valor={brl(p.resumo.lucro)} detalhe={`Margem ${p.resumo.margem.toFixed(1)}% · custo ${brl(p.resumo.custo)}`} />
          <Card titulo="Serviços finalizados" valor={brl(p.resumo.servicos_total)} detalhe={`${p.resumo.servicos} ordens de serviço`} />
          <Card titulo="Estoque a preço de custo" valor={brl(p.estoque.custo)} detalhe={`${p.estoque.itens} produtos · ${p.estoque.baixo} em nível baixo`} />
        </div>

        <Bloco titulo="Vendas por dia">
          {p.resumo.vendas === 0 ? <Vazio /> : (
            <div className="flex h-48 items-end gap-1 overflow-x-auto">
              {p.vendasPorDia.map((d) => (
                <div key={d.dia} className="flex min-w-6 flex-1 flex-col items-center gap-1" title={`${d.dia}: ${brl(d.total)}`}>
                  <div className="w-full rounded-t bg-[#C9281F]" style={{ height: `${(d.total / maxDia) * 160}px` }} />
                  <span className="text-[10px] text-neutral-500">{d.dia}</span>
                </div>
              ))}
            </div>
          )}
        </Bloco>

        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2">
            <Bloco titulo="Produtos mais vendidos">
              {p.maisVendidos.length === 0 ? <Vazio /> : (
                <table className="w-full text-sm">
                  <thead className="text-left text-xs uppercase text-neutral-500">
                    <tr><th className="py-2">Produto</th><th className="text-right">Qtd</th><th className="text-right">Receita</th><th className="text-right">Lucro</th></tr>
                  </thead>
                  <tbody className="divide-y divide-neutral-100">
                    {p.maisVendidos.map((m) => (
                      <tr key={m.codigo}>
                        <td className="py-2"><span className="font-mono text-xs text-neutral-500">{m.codigo}</span> {m.nome}</td>
                        <td className="text-right">{num(m.quantidade)}</td>
                        <td className="text-right">{brl(m.receita)}</td>
                        <td className={`text-right ${m.lucro < 0 ? 'text-[#C9281F]' : ''}`}>{brl(m.lucro)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Bloco>
          </div>
          <Bloco titulo="Formas de pagamento">
            {p.formas.length === 0 ? <Vazio /> : (
              <ul className="space-y-3 text-sm">
                {p.formas.map((f) => (
                  <li key={f.forma}>
                    <div className="flex justify-between"><span>{FORMAS[f.forma] ?? f.forma} ({f.qtd})</span><span className="font-semibold">{brl(f.total)}</span></div>
                    <div className="mt-1 h-2 rounded bg-neutral-100">
                      <div className="h-2 rounded bg-[#C9281F]" style={{ width: `${(f.total / (p.resumo.faturamento || 1)) * 100}%` }} />
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Bloco>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          <Bloco titulo="Serviços abertos no período, por status">
            {p.servicosStatus.length === 0 ? <Vazio /> : (
              <table className="w-full text-sm">
                <tbody className="divide-y divide-neutral-100">
                  {p.servicosStatus.map((s) => (
                    <tr key={s.status}><td className="py-2">{s.status}</td><td className="text-right">{s.qtd}</td><td className="text-right">{brl(s.total)}</td></tr>
                  ))}
                </tbody>
              </table>
            )}
          </Bloco>
          <Bloco titulo="Serviços finalizados, por mecânico">
            {p.mecanicos.length === 0 ? <Vazio /> : (
              <table className="w-full text-sm">
                <thead className="text-left text-xs uppercase text-neutral-500">
                  <tr><th className="py-2">Mecânico</th><th className="text-right">OS</th><th className="text-right">Mão de obra</th><th className="text-right">Total</th></tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {p.mecanicos.map((m) => (
                    <tr key={m.mecanico}><td className="py-2">{m.mecanico}</td><td className="text-right">{m.qtd}</td><td className="text-right">{brl(m.mao_obra)}</td><td className="text-right">{brl(m.total)}</td></tr>
                  ))}
                </tbody>
              </table>
            )}
          </Bloco>
        </div>

        <p className="text-xs text-neutral-500">
          Estoque atual a preço de venda: {brl(p.estoque.venda)}. Lucro bruto = faturamento − custo dos itens no momento da venda (descontos já abatidos).
        </p>
      </div>
    </AppLayout>
  );
}