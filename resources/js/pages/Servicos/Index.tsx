import { useState, type FormEvent, type ReactNode } from "react";
import { Link, useForm } from "@inertiajs/react";
import { Plus, Search, Trash2, Wrench, Clock, Package, CheckCircle } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Select, Card, StatCard, Table, Th, Td, StatusBadge, Modal } from "@/components/ui";
import { formatarPlaca } from "@/components/Cadastros";

type Ordem = { id: number; data: string; cliente: string; moto: string; placa: string; servico: string; mecanico: string; total: number; status: string };
type ClienteOS = { id: number; nome: string; motos: { id: number; descricao: string; km_atual: number }[] };
type ProdutoOS = { id: number; nome: string; codigo: string; unidade: string; saldo: number; preco_venda: number };
type Props = { ordens: Ordem[]; contagem: Record<string, number>; clientes: ClienteOS[]; produtos: ProdutoOS[] };

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);
const num = (v: string) => parseFloat(v.replace(/\./g, "").replace(",", ".")) || 0;

function Campo({ label, erro, children, className = "" }: { label: string; erro?: string; children: ReactNode; className?: string }) {
  return (
    <div className={className}>
      <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>{label}</label>
      {children}
      {erro && <p className="text-red text-xs mt-1">{erro}</p>}
    </div>
  );
}

function NovoServico({ clientes, produtos, onClose }: { clientes: ClienteOS[]; produtos: ProdutoOS[]; onClose: () => void }) {
  const [busca, setBusca] = useState("");
  const { data, setData, post, processing, errors } = useForm<{
    cliente_id: string; motocicleta_id: string; km_entrada: string; problema: string;
    descricao_servico: string; valor_mao_obra: string; mecanico: string;
    pecas: { produto_id: number; quantidade: number }[];
  }>({ cliente_id: "", motocicleta_id: "", km_entrada: "", problema: "", descricao_servico: "", valor_mao_obra: "", mecanico: "", pecas: [] });

  const cliente = clientes.find((c) => c.id === Number(data.cliente_id));
  const porId = (id: number) => produtos.find((p) => p.id === id)!;
  const resultados = busca
    ? produtos.filter((p) => `${p.nome} ${p.codigo}`.toLowerCase().includes(busca.toLowerCase())).slice(0, 5)
    : [];

  const escolherMoto = (id: string) => {
    const moto = cliente?.motos.find((m) => m.id === Number(id));
    setData((d) => ({ ...d, motocicleta_id: id, km_entrada: moto ? String(moto.km_atual) : d.km_entrada }));
  };
  const adicionar = (p: ProdutoOS) => {
    const ex = data.pecas.find((i) => i.produto_id === p.id);
    if (ex && ex.quantidade >= p.saldo) return;
    setData("pecas", ex ? data.pecas.map((i) => (i.produto_id === p.id ? { ...i, quantidade: i.quantidade + 1 } : i)) : [...data.pecas, { produto_id: p.id, quantidade: 1 }]);
    setBusca("");
  };

  const totalPecas = data.pecas.reduce((s, i) => s + porId(i.produto_id).preco_venda * i.quantidade, 0);
  const maoObra = num(data.valor_mao_obra);

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    post("/servicos", { preserveScroll: true });
  };

  return (
    <form onSubmit={salvar} className="space-y-5">
      <div className="grid grid-cols-2 gap-4">
        <Campo label="Cliente *" erro={errors.cliente_id}>
          <Select value={data.cliente_id} onChange={(v) => setData((d) => ({ ...d, cliente_id: v, motocicleta_id: "", km_entrada: "" }))} className="w-full">
            <option value="">Selecionar cliente...</option>
            {clientes.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
          </Select>
        </Campo>
        <Campo label="Motocicleta *" erro={errors.motocicleta_id}>
          <Select value={data.motocicleta_id} onChange={escolherMoto} className="w-full" disabled={!cliente}>
            <option value="">{cliente && cliente.motos.length === 0 ? "Cliente sem motos cadastradas" : "Selecionar moto..."}</option>
            {cliente?.motos.map((m) => <option key={m.id} value={m.id}>{m.descricao}</option>)}
          </Select>
        </Campo>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <Campo label="Quilometragem de entrada" erro={errors.km_entrada}>
          <Input value={data.km_entrada} onChange={(v) => setData("km_entrada", v)} placeholder="38420" />
        </Campo>
        <Campo label="Mecânico" erro={errors.mecanico}>
          <Input value={data.mecanico} onChange={(v) => setData("mecanico", v)} placeholder="Nome de quem vai executar" />
        </Campo>
      </div>

      <Campo label="Problema relatado" erro={errors.problema}>
        <textarea
          value={data.problema}
          onChange={(e) => setData("problema", e.target.value)}
          rows={2}
          placeholder="O que o cliente relatou..."
          className="w-full px-3 py-2 rounded-md border text-sm text-charcoal focus:outline-none focus:ring-1 focus:ring-red resize-none"
          style={{ borderColor: "#D8D4CC" }}
        />
      </Campo>

      <div className="pt-1" style={{ borderTop: "1px solid #F0EDE5" }}>
        <div className="text-xs font-semibold text-gray-text uppercase mb-3 mt-3" style={{ letterSpacing: "0.07em" }}>Mão de obra</div>
        <div className="grid grid-cols-3 gap-3">
          <Input className="col-span-2" placeholder="Descrição do serviço" value={data.descricao_servico} onChange={(v) => setData("descricao_servico", v)} />
          <Input placeholder="R$ 0,00" value={data.valor_mao_obra} onChange={(v) => setData("valor_mao_obra", v)} />
        </div>
        {errors.valor_mao_obra && <p className="text-red text-xs mt-1">{errors.valor_mao_obra}</p>}
      </div>

      <div className="pt-1" style={{ borderTop: "1px solid #F0EDE5" }}>
        <div className="text-xs font-semibold text-gray-text uppercase mb-3 mt-3" style={{ letterSpacing: "0.07em" }}>Peças utilizadas (baixam do estoque ao abrir)</div>
        <div className="relative mb-3">
          <Input placeholder="Buscar peça por nome ou código..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
          {resultados.length > 0 && (
            <div className="absolute top-full mt-1 left-0 right-0 bg-white rounded-lg shadow-xl z-10 py-1" style={{ border: "1px solid #E5E2DA" }}>
              {resultados.map((p) => (
                <button key={p.id} type="button" onClick={() => adicionar(p)} className="w-full flex items-center justify-between px-4 py-2.5 hover:bg-cream text-left">
                  <div>
                    <div className="text-charcoal text-sm font-medium">{p.nome}</div>
                    <div className="text-gray-text text-xs">{p.codigo} · {p.saldo.toLocaleString("pt-BR")} {p.unidade.toLowerCase()} em estoque</div>
                  </div>
                  <div style={{ fontFamily: "var(--font-condensed)", fontWeight: 600, fontSize: 15 }}>{brl(p.preco_venda)}</div>
                </button>
              ))}
            </div>
          )}
        </div>
        {errors.pecas && <p className="text-red text-xs mb-2">{errors.pecas}</p>}
        {data.pecas.map((i) => {
          const p = porId(i.produto_id);
          return (
            <div key={p.id} className="flex items-center gap-3 py-2" style={{ borderBottom: "1px solid #F0EDE5" }}>
              <div className="flex-1 text-sm text-charcoal">{p.nome}</div>
              <input
                type="number"
                min={1}
                max={p.saldo}
                value={i.quantidade}
                onChange={(e) => {
                  const q = Math.min(Number(e.target.value) || 0, p.saldo);
                  setData("pecas", q <= 0 ? data.pecas.filter((x) => x.produto_id !== p.id) : data.pecas.map((x) => (x.produto_id === p.id ? { ...x, quantidade: q } : x)));
                }}
                className="w-14 text-center border rounded px-1 py-0.5 text-sm"
                style={{ borderColor: "#D8D4CC", fontFamily: "var(--font-mono)" }}
              />
              <div className="w-24 text-right text-sm" style={{ fontFamily: "var(--font-mono)" }}>{brl(p.preco_venda * i.quantidade)}</div>
              <button type="button" onClick={() => setData("pecas", data.pecas.filter((x) => x.produto_id !== p.id))} className="text-gray-text hover:text-red"><Trash2 size={13} /></button>
            </div>
          );
        })}
      </div>

      <div className="rounded-lg p-4" style={{ background: "#171918" }}>
        <div className="flex justify-between text-sm mb-1" style={{ color: "#9A9D9B" }}><span>Peças</span><span style={{ fontFamily: "var(--font-mono)" }}>{brl(totalPecas)}</span></div>
        <div className="flex justify-between text-sm mb-3" style={{ color: "#9A9D9B" }}><span>Mão de obra</span><span style={{ fontFamily: "var(--font-mono)" }}>{brl(maoObra)}</span></div>
        <div className="flex justify-between items-center pt-2" style={{ borderTop: "1px solid rgba(255,255,255,0.1)" }}>
          <span className="text-white font-semibold">Total</span>
          <span className="text-white" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 22 }}>{brl(totalPecas + maoObra)}</span>
        </div>
      </div>

      <div className="flex justify-end gap-3">
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing}>{processing ? "Abrindo..." : "Abrir serviço"}</Button>
      </div>
    </form>
  );
}

export default function ServicosIndex({ ordens, contagem, clientes, produtos }: Props) {
  const [busca, setBusca] = useState("");
  const [status, setStatus] = useState("");
  const [novo, setNovo] = useState(false);

  const filtradas = ordens.filter((o) => {
    const q = busca.toLowerCase();
    return (!q || `${o.cliente} ${o.moto} ${o.placa}`.toLowerCase().includes(q)) && (!status || o.status === status);
  });

  return (
    <AppLayout title="Serviços">
      <PageHeader title="Serviços" subtitle="Ordens de serviço da oficina.">
        <Button variant="primary" onClick={() => setNovo(true)}><Plus size={14} /> Novo serviço</Button>
      </PageHeader>

      <div className="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <StatCard label="Em aberto" value={`${contagem.aberta ?? 0}`} icon={Clock} />
        <StatCard label="Em andamento" value={`${contagem.em_andamento ?? 0}`} icon={Wrench} variant="success" />
        <StatCard label="Aguardando peça" value={`${contagem.aguardando_peca ?? 0}`} icon={Package} variant="warning" />
        <StatCard label="Finalizados" value={`${contagem.finalizada ?? 0}`} icon={CheckCircle} />
      </div>

      <div className="flex items-center gap-3 mb-5">
        <Input className="w-64" placeholder="Buscar cliente, moto ou placa..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
        <Select value={status} onChange={setStatus}>
          <option value="">Todos os status</option>
          {["Aberto", "Em andamento", "Aguardando peça", "Finalizado", "Cancelado"].map((s) => <option key={s} value={s}>{s}</option>)}
        </Select>
        <span className="text-gray-text text-sm ml-auto">{filtradas.length} serviços</span>
      </div>

      <Card>
        <Table>
          <thead>
            <tr><Th>#</Th><Th>Cliente</Th><Th>Motocicleta</Th><Th>Placa</Th><Th>Serviço</Th><Th>Data</Th><Th className="text-right">Valor</Th><Th>Status</Th><Th>Mecânico</Th></tr>
          </thead>
          <tbody>
            {filtradas.map((o) => (
              <tr key={o.id} className="hover:bg-cream/40 transition-fast">
                <Td><Link href={`/servicos/${o.id}`} className="text-red hover:underline" style={{ fontFamily: "var(--font-mono)", fontSize: 12 }}>#{o.id}</Link></Td>
                <Td><Link href={`/servicos/${o.id}`} className="font-medium text-charcoal hover:underline">{o.cliente}</Link></Td>
                <Td><span className="text-gray-text text-sm">{o.moto}</span></Td>
                <Td><span className="px-2 py-0.5 rounded text-xs" style={{ background: "#F0EDE5", color: "#777A78", fontFamily: "var(--font-mono)" }}>{formatarPlaca(o.placa)}</span></Td>
                <Td><span className="text-charcoal text-sm">{o.servico}</span></Td>
                <Td><span className="text-gray-text text-sm">{o.data}</span></Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-condensed)", fontWeight: 600, fontSize: 15 }}>{brl(o.total)}</span></Td>
                <Td><StatusBadge status={o.status} /></Td>
                <Td><span className="text-gray-text text-sm">{o.mecanico}</span></Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {filtradas.length === 0 && <div className="py-12 text-center text-gray-text text-sm">Nenhum serviço encontrado.</div>}
      </Card>

      {novo && (
        <Modal title="Novo serviço" onClose={() => setNovo(false)} width="max-w-2xl">
          <NovoServico clientes={clientes} produtos={produtos} onClose={() => setNovo(false)} />
        </Modal>
      )}
    </AppLayout>
  );
}