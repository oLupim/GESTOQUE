import { useState, type FormEvent } from "react";
import { useForm } from "@inertiajs/react";
import { Plus, Search, ArrowDown, ArrowUp, Package, AlertTriangle, XCircle, DollarSign } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Card, StatCard, Table, Th, Td, Tabs, Modal, Select, Badge, StatusBadge } from "@/components/ui";

type Produto = {
  id: number; codigo: string; nome: string; categoria: string | null; unidade: string;
  saldo: number; estoque_minimo: number; situacao: string; ultima_movimentacao: string | null;
};
type Mov = {
  id: number; data: string; produto: string; tipo: string; tipo_label: string; quantidade: number;
  saldo_anterior: number; saldo_posterior: number; origem: string; motivo: string | null; usuario: string;
};
type Tipo = { value: string; label: string; entrada: boolean; exige_motivo: boolean };
type Props = {
  produtos: Produto[];
  movimentacoes: Mov[];
  resumo: { total: number; valor: number; baixo: number; sem_estoque: number };
  tipos: Tipo[];
};

const qtd = (v: number) => v.toLocaleString("pt-BR", { maximumFractionDigits: 3 });
const brl0 = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 }).format(v);
const corSituacao: Record<string, string> = { Normal: "#1A7C4E", Baixo: "#D4610D", "Crítico": "#C9281F", "Sem estoque": "#9A9D9B" };

function Rotulo({ children }: { children: string }) {
  return (
    <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>
      {children}
    </label>
  );
}

function MovModal({ produtos, tipos, produtoInicial, onClose }: { produtos: Produto[]; tipos: Tipo[]; produtoInicial: number | null; onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm({
    produto_id: produtoInicial ? String(produtoInicial) : "",
    tipo: tipos[0]?.value ?? "ENTRADA",
    quantidade: "",
    motivo: "",
  });

  const produto = produtos.find((p) => p.id === Number(data.produto_id));
  const tipo = tipos.find((t) => t.value === data.tipo);
  const n = parseFloat(data.quantidade.replace(",", ".")) || 0;
  const novo = produto ? (tipo?.entrada ? produto.saldo + n : produto.saldo - n) : 0;

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    post("/estoque/movimentacoes", { preserveScroll: true, onSuccess: () => onClose() });
  };

  return (
    <form onSubmit={salvar} className="space-y-5">
      <div>
        <Rotulo>Produto</Rotulo>
        <Select value={data.produto_id} onChange={(v) => setData("produto_id", v)} className="w-full">
          <option value="">Selecionar produto...</option>
          {produtos.map((p) => (
            <option key={p.id} value={p.id}>{p.nome} ({p.codigo}) — {qtd(p.saldo)} {p.unidade.toLowerCase()}</option>
          ))}
        </Select>
        {errors.produto_id && <p className="text-red text-xs mt-1">{errors.produto_id}</p>}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <Rotulo>Tipo de movimentação</Rotulo>
          <Select value={data.tipo} onChange={(v) => setData("tipo", v)} className="w-full">
            {tipos.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
          </Select>
        </div>
        <div>
          <Rotulo>Quantidade</Rotulo>
          <Input placeholder="0" value={data.quantidade} onChange={(v) => setData("quantidade", v)} />
          {errors.quantidade && <p className="text-red text-xs mt-1">{errors.quantidade}</p>}
        </div>
      </div>

      <div>
        <Rotulo>{tipo?.exige_motivo ? "Motivo *" : "Motivo / observação"}</Rotulo>
        <textarea
          value={data.motivo}
          onChange={(e) => setData("motivo", e.target.value)}
          rows={2}
          placeholder="Ex.: embalagem danificada, diferença no inventário..."
          className="w-full px-3 py-2 rounded-md border text-charcoal text-sm focus:outline-none focus:ring-1 focus:ring-red resize-none"
          style={{ borderColor: "#D8D4CC" }}
        />
        {errors.motivo && <p className="text-red text-xs mt-1">{errors.motivo}</p>}
      </div>

      {produto && n > 0 && (
        <div className="rounded-lg p-4 flex items-center justify-between gap-4" style={{ background: "#F9F7F3", border: "1px solid #E5E2DA" }}>
          <div className="text-center">
            <div className="text-xs text-gray-text mb-1">Estoque atual</div>
            <div style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 24 }}>{qtd(produto.saldo)}</div>
          </div>
          <div
            className="px-3 py-1 rounded-full text-xs font-semibold"
            style={{
              background: tipo?.entrada ? "rgba(26,124,78,0.12)" : "rgba(201,40,31,0.10)",
              color: tipo?.entrada ? "#1A7C4E" : "#C9281F",
            }}
          >
            {tipo?.entrada ? "+" : "−"}{qtd(n)}
          </div>
          <div className="text-center">
            <div className="text-xs text-gray-text mb-1">Novo estoque</div>
            <div style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 24, color: novo < 0 ? "#C9281F" : "#171918" }}>
              {novo < 0 ? "Insuficiente" : qtd(novo)}
            </div>
          </div>
        </div>
      )}

      <div className="flex items-center justify-end gap-3 pt-1" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing || !produto || n <= 0}>
          {processing ? "Registrando..." : "Confirmar movimentação"}
        </Button>
      </div>
    </form>
  );
}

export default function EstoqueIndex({ produtos, movimentacoes, resumo, tipos }: Props) {
  const [aba, setAba] = useState("overview");
  const [busca, setBusca] = useState("");
  const [modal, setModal] = useState<{ aberto: boolean; produto: number | null }>({ aberto: false, produto: null });

  const abrir = (produto: number | null = null) => setModal({ aberto: true, produto });
  const filtrados = produtos.filter((p) => {
    const q = busca.toLowerCase();
    return !q || p.nome.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q);
  });
  const baixos = produtos.filter((p) => p.situacao !== "Normal");

  return (
    <AppLayout title="Estoque">
      <PageHeader title="Controle de estoque" subtitle="Posição atual e histórico de todas as movimentações.">
        <Button variant="primary" onClick={() => abrir()}>
          <Plus size={14} /> Nova movimentação
        </Button>
      </PageHeader>

      <div className="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <StatCard label="Total de produtos" value={`${resumo.total}`} sub="cadastrados" icon={Package} />
        <StatCard label="Valor em estoque" value={brl0(resumo.valor)} sub="pelo preço de custo" icon={DollarSign} variant="success" />
        <StatCard label="Estoque baixo" value={`${resumo.baixo}`} sub="no mínimo ou abaixo" icon={AlertTriangle} variant="warning" />
        <StatCard label="Sem estoque" value={`${resumo.sem_estoque}`} sub="produtos zerados" icon={XCircle} variant="danger" />
      </div>

      <Tabs
        tabs={[
          { id: "overview", label: "Visão geral" },
          { id: "movements", label: "Movimentações", count: movimentacoes.length },
          { id: "low", label: "Estoque baixo", count: baixos.length },
        ]}
        active={aba}
        onChange={setAba}
      />

      {aba === "overview" && (
        <Card>
          <div className="px-5 py-3.5 flex items-center gap-3" style={{ borderBottom: "1px solid #F0EDE5" }}>
            <Input className="w-64" placeholder="Buscar produto ou código..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
            <span className="text-gray-text text-sm ml-auto">{filtrados.length} produtos</span>
          </div>
          <Table>
            <thead>
              <tr>
                <Th>Produto</Th><Th>Código</Th><Th>Categoria</Th>
                <Th className="text-right">Estoque</Th><Th className="text-right">Mínimo</Th>
                <Th>Última movimentação</Th><Th>Status</Th><Th></Th>
              </tr>
            </thead>
            <tbody>
              {filtrados.map((p) => (
                <tr key={p.id} className="hover:bg-cream/40 transition-fast group">
                  <Td><span className="font-medium text-charcoal">{p.nome}</span></Td>
                  <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{p.codigo}</span></Td>
                  <Td><span className="text-gray-text text-sm">{p.categoria ?? "—"}</span></Td>
                  <Td className="text-right">
                    <span style={{ fontFamily: "var(--font-mono)", fontWeight: 600, color: corSituacao[p.situacao] }}>{qtd(p.saldo)}</span>
                    <span className="text-gray-text text-xs ml-1">{p.unidade.toLowerCase()}</span>
                  </Td>
                  <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{qtd(p.estoque_minimo)}</span></Td>
                  <Td><span className="text-gray-text text-xs">{p.ultima_movimentacao ?? "—"}</span></Td>
                  <Td><StatusBadge status={p.situacao} /></Td>
                  <Td>
                    <button
                      onClick={() => abrir(p.id)}
                      className="opacity-0 group-hover:opacity-100 text-gray-text hover:text-charcoal transition-fast text-xs flex items-center gap-1 px-2 py-1 rounded hover:bg-cream"
                    >
                      <Plus size={11} /> Movimentar
                    </button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Card>
      )}

      {aba === "movements" && (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>Data</Th><Th>Produto</Th><Th>Tipo</Th><Th>Origem / motivo</Th>
                <Th className="text-right">Qtd.</Th><Th className="text-right">Anterior</Th><Th className="text-right">Resultante</Th><Th>Usuário</Th>
              </tr>
            </thead>
            <tbody>
              {movimentacoes.map((m) => {
                const cor = m.quantidade > 0 ? "#1A7C4E" : m.tipo.startsWith("AJUSTE") ? "#D4610D" : "#C9281F";
                return (
                  <tr key={m.id} className="hover:bg-cream/40 transition-fast">
                    <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12 }}>{m.data}</span></Td>
                    <Td><span className="text-charcoal font-medium">{m.produto}</span></Td>
                    <Td>
                      <span className="flex items-center gap-1.5 text-xs" style={{ color: cor }}>
                        {m.quantidade > 0 ? <ArrowUp size={11} /> : <ArrowDown size={11} />}
                        {m.tipo_label}
                      </span>
                    </Td>
                    <Td>
                      <div className="text-gray-text text-sm">{m.origem}</div>
                      {m.motivo && <div className="text-gray-muted text-xs">{m.motivo}</div>}
                    </Td>
                    <Td className="text-right">
                      <span style={{ fontFamily: "var(--font-mono)", fontWeight: 600, color: m.quantidade > 0 ? "#1A7C4E" : "#C9281F" }}>
                        {m.quantidade > 0 ? "+" : ""}{qtd(m.quantidade)}
                      </span>
                    </Td>
                    <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{qtd(m.saldo_anterior)}</span></Td>
                    <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontWeight: 500 }}>{qtd(m.saldo_posterior)}</span></Td>
                    <Td><span className="text-gray-text text-sm">{m.usuario}</span></Td>
                  </tr>
                );
              })}
            </tbody>
          </Table>
          {movimentacoes.length === 0 && <div className="py-12 text-center text-gray-text text-sm">Nenhuma movimentação ainda.</div>}
        </Card>
      )}

      {aba === "low" && (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>Produto</Th><Th>Código</Th><Th className="text-right">Atual</Th>
                <Th className="text-right">Mínimo</Th><Th className="text-right">Déficit</Th><Th>Status</Th><Th></Th>
              </tr>
            </thead>
            <tbody>
              {baixos.map((p) => (
                <tr key={p.id} className="hover:bg-cream/40 transition-fast">
                  <Td><span className="font-medium text-charcoal">{p.nome}</span></Td>
                  <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{p.codigo}</span></Td>
                  <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontWeight: 600, color: corSituacao[p.situacao] }}>{qtd(p.saldo)}</span></Td>
                  <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{qtd(p.estoque_minimo)}</span></Td>
                  <Td className="text-right">
                    <span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#C9281F" }}>−{qtd(Math.max(0, p.estoque_minimo - p.saldo))}</span>
                  </Td>
                  <Td>{p.saldo <= 0 ? <Badge variant="danger">Sem estoque</Badge> : <StatusBadge status={p.situacao} />}</Td>
                  <Td>
                    <Button variant="secondary" size="sm" onClick={() => abrir(p.id)}>
                      <Plus size={11} /> Registrar entrada
                    </Button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Card>
      )}

      {modal.aberto && (
        <Modal title="Nova movimentação de estoque" onClose={() => setModal({ aberto: false, produto: null })}>
          <MovModal produtos={produtos} tipos={tipos} produtoInicial={modal.produto} onClose={() => setModal({ aberto: false, produto: null })} />
        </Modal>
      )}
    </AppLayout>
  );
}