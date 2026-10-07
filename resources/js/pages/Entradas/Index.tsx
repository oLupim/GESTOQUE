import { useState, type FormEvent } from "react";
import { useForm } from "@inertiajs/react";
import { Plus, Search, Trash2 } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Card, Table, Th, Td, Modal, Select } from "@/components/ui";

type Entrada = { id: number; data: string; fornecedor: string | null; documento: string | null; itens: number; resumo: string; total: number; usuario: string };
type Produto = { id: number; nome: string; codigo: string; unidade: string; saldo: number; preco_custo: number };
type Item = { produto_id: string; quantidade: string; custo_unitario: string };
type Props = { entradas: Entrada[]; produtos: Produto[] };

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);
const num = (v: string) => parseFloat(v.replace(",", ".")) || 0;
const hoje = () => new Date().toLocaleDateString("sv-SE"); // AAAA-MM-DD no fuso local

function Rotulo({ children }: { children: string }) {
  return <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>{children}</label>;
}

function Erro({ msg }: { msg?: string }) {
  return msg ? <p className="text-red text-xs mt-1">{msg}</p> : null;
}

function NovaEntrada({ produtos, onClose }: { produtos: Produto[]; onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm<{
    fornecedor: string; documento: string; data_entrada: string; itens: Item[];
  }>({
    fornecedor: "",
    documento: "",
    data_entrada: hoje(),
    itens: [{ produto_id: "", quantidade: "", custo_unitario: "" }],
  });
  const erro = errors as Record<string, string | undefined>;

  const alterarItem = (i: number, campo: keyof Item, valor: string) => {
    const itens = data.itens.map((it, idx) => (idx === i ? { ...it, [campo]: valor } : it));
    // Ao escolher o produto, sugere o último custo cadastrado.
    if (campo === "produto_id" && !itens[i].custo_unitario) {
      const p = produtos.find((p) => p.id === Number(valor));
      if (p && p.preco_custo > 0) itens[i].custo_unitario = p.preco_custo.toFixed(2).replace(".", ",");
    }
    setData("itens", itens);
  };
  const adicionar = () => setData("itens", [...data.itens, { produto_id: "", quantidade: "", custo_unitario: "" }]);
  const remover = (i: number) => setData("itens", data.itens.filter((_, idx) => idx !== i));

  const total = data.itens.reduce((s, it) => s + num(it.quantidade) * num(it.custo_unitario), 0);

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    post("/entradas", { preserveScroll: true, onSuccess: () => onClose() });
  };

  return (
    <form onSubmit={salvar} className="space-y-5">
      <div className="grid grid-cols-3 gap-4">
        <div>
          <Rotulo>Fornecedor</Rotulo>
          <Input placeholder="Ex.: Distribuidora X" value={data.fornecedor} onChange={(v) => setData("fornecedor", v)} />
          <Erro msg={erro.fornecedor} />
        </div>
        <div>
          <Rotulo>Nº da nota</Rotulo>
          <Input placeholder="Ex.: 12345" value={data.documento} onChange={(v) => setData("documento", v)} />
          <Erro msg={erro.documento} />
        </div>
        <div>
          <Rotulo>Data *</Rotulo>
          <Input type="date" value={data.data_entrada} onChange={(v) => setData("data_entrada", v)} />
          <Erro msg={erro.data_entrada} />
        </div>
      </div>

      <div className="pt-2" style={{ borderTop: "1px solid #F0EDE5" }}>
        <div className="flex items-center justify-between mb-3">
          <div className="text-xs font-semibold text-gray-text uppercase" style={{ letterSpacing: "0.07em" }}>Produtos recebidos</div>
          <Button variant="secondary" size="sm" onClick={adicionar}><Plus size={11} /> Adicionar item</Button>
        </div>
        <Erro msg={erro.itens} />

        <div className="space-y-3">
          {data.itens.map((it, i) => {
            const p = produtos.find((p) => p.id === Number(it.produto_id));
            return (
              <div key={i} className="grid gap-2 items-start" style={{ gridTemplateColumns: "1fr 90px 110px 100px 32px" }}>
                <div>
                  <Select value={it.produto_id} onChange={(v) => alterarItem(i, "produto_id", v)} className="w-full">
                    <option value="">Selecionar produto...</option>
                    {produtos.map((p) => <option key={p.id} value={p.id}>{p.nome} ({p.codigo})</option>)}
                  </Select>
                  {p && <div className="text-gray-text text-xs mt-1">Estoque atual: {p.saldo.toLocaleString("pt-BR")} {p.unidade.toLowerCase()}</div>}
                  <Erro msg={erro[`itens.${i}.produto_id`]} />
                </div>
                <div>
                  <Input placeholder="Qtd." value={it.quantidade} onChange={(v) => alterarItem(i, "quantidade", v)} />
                  <Erro msg={erro[`itens.${i}.quantidade`]} />
                </div>
                <div>
                  <Input placeholder="Custo un." value={it.custo_unitario} onChange={(v) => alterarItem(i, "custo_unitario", v)} />
                  <Erro msg={erro[`itens.${i}.custo_unitario`]} />
                </div>
                <div className="py-2 text-right text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                  {brl(num(it.quantidade) * num(it.custo_unitario))}
                </div>
                <button
                  type="button"
                  onClick={() => remover(i)}
                  disabled={data.itens.length === 1}
                  className="py-2 text-gray-text hover:text-red transition-fast disabled:opacity-30"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            );
          })}
        </div>
      </div>

      <div className="flex items-center justify-between rounded-lg px-4 py-3" style={{ background: "#171918" }}>
        <span className="text-white font-semibold">Total da entrada</span>
        <span className="text-white" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 22 }}>{brl(total)}</span>
      </div>

      <div className="flex items-center justify-end gap-3 pt-1" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing}>
          {processing ? "Registrando..." : "Registrar entrada"}
        </Button>
      </div>
    </form>
  );
}

export default function EntradasIndex({ entradas, produtos }: Props) {
  const [busca, setBusca] = useState("");
  const [nova, setNova] = useState(false);

  const filtradas = entradas.filter((e) => {
    const q = busca.toLowerCase();
    return !q || (e.fornecedor ?? "").toLowerCase().includes(q) || (e.documento ?? "").toLowerCase().includes(q) || e.resumo.toLowerCase().includes(q);
  });

  return (
    <AppLayout title="Entradas">
      <PageHeader title="Entradas de mercadoria" subtitle="Registre as compras recebidas. Cada item gera uma movimentação de estoque.">
        <Button variant="primary" onClick={() => setNova(true)}><Plus size={14} /> Nova entrada</Button>
      </PageHeader>

      <div className="flex items-center gap-3 mb-5">
        <Input className="w-72" placeholder="Buscar fornecedor, nota ou produto..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
        <span className="text-gray-text text-sm ml-auto">{filtradas.length} entradas</span>
      </div>

      <Card>
        <Table>
          <thead>
            <tr>
              <Th>#</Th><Th>Data</Th><Th>Fornecedor</Th><Th>Nota</Th><Th>Produtos</Th>
              <Th className="text-right">Total</Th><Th>Usuário</Th>
            </tr>
          </thead>
          <tbody>
            {filtradas.map((e) => (
              <tr key={e.id} className="hover:bg-cream/40 transition-fast">
                <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>#{e.id}</span></Td>
                <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12 }}>{e.data}</span></Td>
                <Td><span className="font-medium text-charcoal">{e.fornecedor ?? "—"}</span></Td>
                <Td><span className="text-gray-text text-sm">{e.documento ?? "—"}</span></Td>
                <Td>
                  <div className="text-charcoal text-sm">{e.itens} {e.itens === 1 ? "item" : "itens"}</div>
                  <div className="text-gray-text text-xs truncate" style={{ maxWidth: 360 }}>{e.resumo}</div>
                </Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontWeight: 500 }}>{brl(e.total)}</span></Td>
                <Td><span className="text-gray-text text-sm">{e.usuario}</span></Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {filtradas.length === 0 && <div className="py-12 text-center text-gray-text text-sm">Nenhuma entrada registrada.</div>}
      </Card>

      {nova && (
        <Modal title="Nova entrada de mercadoria" onClose={() => setNova(false)} width="max-w-3xl">
          <NovaEntrada produtos={produtos} onClose={() => setNova(false)} />
        </Modal>
      )}
    </AppLayout>
  );
}