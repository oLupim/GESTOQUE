import { useState, type FormEvent } from "react";
import { Link, router, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, Bike, Phone, Trash2, AlertTriangle, Plus } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { Button, Input, Select, Card, StatusBadge, Modal } from "@/components/ui";
import { formatarPlaca, formatarTelefone } from "@/components/Cadastros";

type Item = { id: number; produto: string; codigo: string; unidade: string; quantidade: number; preco_unitario: number; subtotal: number };
type OS = {
  id: number; status: string; rotulo: string; editavel: boolean; transicoes: { valor: string; rotulo: string }[];
  aberta_em: string; finalizada_em: string | null; motivo_cancelamento: string | null;
  cliente: { nome: string; telefone: string | null }; moto: { descricao: string; placa: string; km_atual: number };
  km_entrada: number | null; mecanico: string | null; problema: string | null; descricao_servico: string | null;
  valor_mao_obra: number; valor_pecas: number; total: number; itens: Item[];
};
type ProdutoOS = { id: number; nome: string; codigo: string; unidade: string; saldo: number; preco_venda: number };

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);

function Titulo({ children }: { children: string }) {
  return <div className="text-xs font-semibold text-gray-text uppercase mb-3" style={{ letterSpacing: "0.07em" }}>{children}</div>;
}

function CancelarOS({ os, onClose }: { os: OS; onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm({ motivo: "" });
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    post(`/servicos/${os.id}/cancelar`, { preserveScroll: true, onSuccess: () => onClose() });
  };
  return (
    <form onSubmit={enviar} className="space-y-4">
      <p className="text-sm text-charcoal">A OS <b>#{os.id}</b> será cancelada e as {os.itens.length} peça(s) voltarão ao estoque. O registro continua no histórico.</p>
      <textarea
        value={data.motivo}
        onChange={(e) => setData("motivo", e.target.value)}
        rows={2}
        placeholder="Motivo do cancelamento *"
        className="w-full px-3 py-2 rounded-md border text-sm focus:outline-none focus:ring-1 focus:ring-red resize-none"
        style={{ borderColor: "#D8D4CC" }}
      />
      {errors.motivo && <p className="text-red text-xs">{errors.motivo}</p>}
      <div className="flex justify-end gap-3">
        <Button variant="secondary" onClick={onClose}>Voltar</Button>
        <Button variant="danger" type="submit" disabled={processing}>Cancelar OS</Button>
      </div>
    </form>
  );
}

export default function ServicoShow({ os, produtos }: { os: OS; produtos: ProdutoOS[] }) {
  const { errors: erroGeral } = usePage<{ errors: Record<string, string> }>().props;
  const [cancelando, setCancelando] = useState(false);

  const maoObra = useForm({
    mecanico: os.mecanico ?? "",
    problema: os.problema ?? "",
    descricao_servico: os.descricao_servico ?? "",
    valor_mao_obra: os.valor_mao_obra.toFixed(2).replace(".", ","),
  });
  const peca = useForm({ produto_id: "", quantidade: "1" });

  const salvarMaoObra = (e: FormEvent) => {
    e.preventDefault();
    maoObra.put(`/servicos/${os.id}`, { preserveScroll: true });
  };
  const adicionarPeca = (e: FormEvent) => {
    e.preventDefault();
    peca.post(`/servicos/${os.id}/pecas`, { preserveScroll: true, onSuccess: () => peca.reset() });
  };
  const removerPeca = (item: Item) => {
    if (confirm(`Remover "${item.produto}" da OS? A peça volta ao estoque.`)) {
      router.delete(`/servicos/${os.id}/pecas/${item.id}`, { preserveScroll: true });
    }
  };
  const mudarStatus = (status: string) => router.post(`/servicos/${os.id}/status`, { status }, { preserveScroll: true });

  return (
    <AppLayout title={`OS #${os.id}`}>
      <Link href="/servicos" className="inline-flex items-center gap-1 text-gray-text hover:text-charcoal text-sm mb-4"><ArrowLeft size={14} /> Serviços</Link>

      <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-charcoal" style={{ fontFamily: "var(--font-condensed)", fontWeight: 800, fontSize: 28 }}>OS #{os.id}</h1>
            <StatusBadge status={os.rotulo} />
          </div>
          <p className="text-gray-text text-sm">Aberta em {os.aberta_em}{os.finalizada_em && ` · finalizada em ${os.finalizada_em}`}</p>
          {os.motivo_cancelamento && <p className="text-red text-sm mt-1">Cancelada: {os.motivo_cancelamento}</p>}
        </div>
        {os.editavel && (
          <div className="flex flex-wrap gap-2">
            {os.transicoes.map((t) => (
              <Button key={t.valor} variant={t.valor === "finalizada" ? "primary" : "secondary"} onClick={() => mudarStatus(t.valor)}>
                {t.valor === "finalizada" ? "Finalizar serviço" : `Marcar: ${t.rotulo}`}
              </Button>
            ))}
            <Button variant="danger" onClick={() => setCancelando(true)}>Cancelar OS</Button>
          </div>
        )}
      </div>

      {erroGeral.os && (
        <div className="flex gap-2 p-3 rounded-lg mb-5 text-sm" style={{ background: "rgba(201,40,31,0.06)", border: "1px solid rgba(201,40,31,0.2)", color: "#C9281F" }}>
          <AlertTriangle size={16} /> {erroGeral.os}
        </div>
      )}

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div className="xl:col-span-2 space-y-5">
          {/* Peças */}
          <Card className="p-5">
            <Titulo>Peças utilizadas</Titulo>
            {os.itens.length === 0 && <div className="text-gray-text text-sm py-3">Nenhuma peça lançada.</div>}
            {os.itens.map((i) => (
              <div key={i.id} className="flex items-center gap-3 py-2.5" style={{ borderBottom: "1px solid #F0EDE5" }}>
                <div className="flex-1">
                  <div className="text-charcoal text-sm font-medium">{i.produto}</div>
                  <div className="text-gray-text text-xs" style={{ fontFamily: "var(--font-mono)" }}>{i.codigo}</div>
                </div>
                <div className="text-sm text-gray-text" style={{ fontFamily: "var(--font-mono)" }}>{i.quantidade.toLocaleString("pt-BR")} × {brl(i.preco_unitario)}</div>
                <div className="w-24 text-right text-sm" style={{ fontFamily: "var(--font-mono)", fontWeight: 500 }}>{brl(i.subtotal)}</div>
                {os.editavel && <button onClick={() => removerPeca(i)} className="text-gray-text hover:text-red" title="Remover e devolver ao estoque"><Trash2 size={14} /></button>}
              </div>
            ))}

            {os.editavel && (
              <form onSubmit={adicionarPeca} className="flex items-start gap-2 mt-4">
                <div className="flex-1">
                  <Select value={peca.data.produto_id} onChange={(v) => peca.setData("produto_id", v)} className="w-full">
                    <option value="">Adicionar peça do estoque...</option>
                    {produtos.map((p) => <option key={p.id} value={p.id}>{p.nome} — {brl(p.preco_venda)} ({p.saldo.toLocaleString("pt-BR")} {p.unidade.toLowerCase()})</option>)}
                  </Select>
                  {(peca.errors.produto_id || peca.errors.quantidade) && <p className="text-red text-xs mt-1">{peca.errors.produto_id ?? peca.errors.quantidade}</p>}
                </div>
                <Input className="w-20" value={peca.data.quantidade} onChange={(v) => peca.setData("quantidade", v)} />
                <Button variant="secondary" type="submit" disabled={peca.processing || !peca.data.produto_id}><Plus size={13} /> Adicionar</Button>
              </form>
            )}
          </Card>

          {/* Mão de obra */}
          <Card className="p-5">
            <Titulo>Serviço e mão de obra</Titulo>
            <form onSubmit={salvarMaoObra} className="space-y-3">
              <textarea
                value={maoObra.data.problema}
                onChange={(e) => maoObra.setData("problema", e.target.value)}
                disabled={!os.editavel}
                rows={2}
                placeholder="Problema relatado pelo cliente"
                className="w-full px-3 py-2 rounded-md border text-sm focus:outline-none focus:ring-1 focus:ring-red resize-none disabled:bg-cream/40"
                style={{ borderColor: "#D8D4CC" }}
              />
              <div className="grid grid-cols-3 gap-3">
                <Input className="col-span-2" placeholder="Descrição do serviço executado" value={maoObra.data.descricao_servico} onChange={(v) => maoObra.setData("descricao_servico", v)} />
                <Input placeholder="Valor mão de obra" value={maoObra.data.valor_mao_obra} onChange={(v) => maoObra.setData("valor_mao_obra", v)} />
              </div>
              <Input placeholder="Mecânico" value={maoObra.data.mecanico} onChange={(v) => maoObra.setData("mecanico", v)} />
              {maoObra.errors.valor_mao_obra && <p className="text-red text-xs">{maoObra.errors.valor_mao_obra}</p>}
              {os.editavel && (
                <div className="flex justify-end">
                  <Button variant="secondary" type="submit" disabled={maoObra.processing || !maoObra.isDirty}>Salvar alterações</Button>
                </div>
              )}
            </form>
          </Card>
        </div>

        {/* Lateral: cliente, moto e totais */}
        <div className="space-y-5">
          <Card className="p-5 space-y-4">
            <div>
              <Titulo>Cliente</Titulo>
              <div className="text-charcoal font-medium">{os.cliente.nome}</div>
              <div className="flex items-center gap-1.5 text-gray-text text-sm mt-1"><Phone size={12} /> {formatarTelefone(os.cliente.telefone)}</div>
            </div>
            <div style={{ borderTop: "1px solid #F0EDE5" }} className="pt-4">
              <Titulo>Motocicleta</Titulo>
              <div className="flex items-center gap-2">
                <Bike size={16} className="text-gray-text" />
                <span className="text-charcoal font-medium">{os.moto.descricao}</span>
              </div>
              <div className="text-gray-text text-sm mt-1">
                <span style={{ fontFamily: "var(--font-mono)" }}>{formatarPlaca(os.moto.placa)}</span> · entrada com {(os.km_entrada ?? os.moto.km_atual).toLocaleString("pt-BR")} km
              </div>
            </div>
          </Card>

          <div className="rounded-lg p-5" style={{ background: "#171918" }}>
            <div className="flex justify-between text-sm mb-1.5" style={{ color: "#9A9D9B" }}><span>Peças</span><span style={{ fontFamily: "var(--font-mono)" }}>{brl(os.valor_pecas)}</span></div>
            <div className="flex justify-between text-sm mb-3" style={{ color: "#9A9D9B" }}><span>Mão de obra</span><span style={{ fontFamily: "var(--font-mono)" }}>{brl(os.valor_mao_obra)}</span></div>
            <div className="flex justify-between items-center pt-3" style={{ borderTop: "1px solid rgba(255,255,255,0.1)" }}>
              <span className="text-white font-semibold">Total</span>
              <span className="text-white" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 26 }}>{brl(os.total)}</span>
            </div>
          </div>
        </div>
      </div>

      {cancelando && (
        <Modal title={`Cancelar OS #${os.id}`} onClose={() => setCancelando(false)}>
          <CancelarOS os={os} onClose={() => setCancelando(false)} />
        </Modal>
      )}
    </AppLayout>
  );
}