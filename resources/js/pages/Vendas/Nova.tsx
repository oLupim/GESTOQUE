import { useEffect, useState, type FormEvent } from "react";
import { Link, useForm, usePage } from "@inertiajs/react";
import { Search, Plus, Minus, Trash2, ChevronRight, CheckCircle, AlertTriangle } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { Button, Input, Card } from "@/components/ui";

type Produto = {
  id: number; nome: string; codigo: string; codigo_barras: string | null; unidade: string;
  preco_venda: number; saldo: number; estoque_minimo: number;
};
type Item = { produto_id: number; quantidade: number };
type VendaFeita = { id: number; total: number; forma: string };
type Props = { produtos: Produto[]; formas: Record<string, string> };

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);
const icones: Record<string, string> = { pix: "⚡", dinheiro: "💵", debito: "💳", credito: "💳" };

function Sucesso({ venda, onNova }: { venda: VendaFeita; onNova: () => void }) {
  return (
    <div className="flex items-center justify-center min-h-[calc(100vh-160px)]">
      <div className="text-center max-w-sm">
        <div className="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" style={{ background: "rgba(26,124,78,0.12)" }}>
          <CheckCircle size={40} style={{ color: "#1A7C4E" }} />
        </div>
        <div className="text-charcoal mb-1" style={{ fontFamily: "var(--font-condensed)", fontWeight: 800, fontSize: 32 }}>Venda realizada!</div>
        <div className="text-gray-text mb-6" style={{ fontSize: 14 }}>Venda #{venda.id} · Pagamento: {venda.forma}</div>
        <div className="text-charcoal mb-8" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 44 }}>{brl(venda.total)}</div>
        <div className="flex items-center justify-center gap-3 flex-wrap">
          <Button variant="primary" onClick={onNova}><Plus size={14} /> Nova venda</Button>
          <Link href="/vendas"><Button variant="secondary">Ver vendas</Button></Link>
          <Button variant="secondary" disabled>Emitir NF-e (em breve)</Button>
        </div>
      </div>
    </div>
  );
}

export default function NovaVenda({ produtos, formas }: Props) {
  const { flash } = usePage<{ flash: { venda: VendaFeita | null } }>().props;
  const [concluida, setConcluida] = useState<VendaFeita | null>(null);
  const [busca, setBusca] = useState("");

  const { data, setData, post, processing, errors, reset, clearErrors } = useForm<{
    forma_pagamento: string; desconto: string; itens: Item[];
  }>({ forma_pagamento: "pix", desconto: "", itens: [] });

  useEffect(() => {
    if (flash?.venda) setConcluida(flash.venda);
  }, [flash]);

  const porId = (id: number) => produtos.find((p) => p.id === id)!;
  const q = busca.trim().toLowerCase();
  const resultados = (q
    ? produtos.filter((p) => p.nome.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q) || p.codigo_barras === busca.trim())
    : produtos
  ).slice(0, 8);

  const adicionar = (p: Produto) => {
    if (p.saldo <= 0) return;
    const existente = data.itens.find((i) => i.produto_id === p.id);
    if (existente && existente.quantidade >= p.saldo) return;
    setData("itens", existente
      ? data.itens.map((i) => (i.produto_id === p.id ? { ...i, quantidade: i.quantidade + 1 } : i))
      : [...data.itens, { produto_id: p.id, quantidade: 1 }]);
    setBusca("");
    clearErrors("itens");
  };
  const alterarQtd = (id: number, delta: number) =>
    setData("itens", data.itens
      .map((i) => (i.produto_id === id ? { ...i, quantidade: Math.min(i.quantidade + delta, porId(id).saldo) } : i))
      .filter((i) => i.quantidade > 0));
  const remover = (id: number) => setData("itens", data.itens.filter((i) => i.produto_id !== id));

  // Enter na busca (ou leitor de código de barras) adiciona o primeiro resultado.
  const enterNaBusca = (e: FormEvent) => {
    e.preventDefault();
    const primeiro = resultados.find((p) => p.saldo > 0);
    if (primeiro) adicionar(primeiro);
  };

  const subtotal = data.itens.reduce((s, i) => s + porId(i.produto_id).preco_venda * i.quantidade, 0);
  const desconto = parseFloat(data.desconto.replace(",", ".")) || 0;
  const total = Math.max(0, subtotal - desconto);

  const finalizar = () => post("/vendas", { preserveScroll: true });
  const novaVenda = () => { reset(); clearErrors(); setConcluida(null); };

  if (concluida) {
    return <AppLayout title="Nova venda"><Sucesso venda={concluida} onNova={novaVenda} /></AppLayout>;
  }

  const erroGeral = errors.itens ?? errors.desconto ?? errors.forma_pagamento ?? (errors as Record<string, string>)["itens.0.quantidade"];

  return (
    <AppLayout title="Nova venda">
      <div className="mb-5">
        <h1 className="text-charcoal leading-tight" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 26 }}>Nova venda</h1>
        <p className="text-gray-text" style={{ fontSize: 13 }}>Venda de balcão · Consumidor final</p>
      </div>

      <div className="flex gap-5 items-start">
        {/* Produtos */}
        <div className="flex-1 min-w-0">
          <Card className="p-4">
            <form onSubmit={enterNaBusca} className="mb-4">
              <Input placeholder="Buscar produto, código ou ler código de barras e apertar Enter..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
            </form>
            <div className="space-y-2">
              {resultados.map((p) => {
                const noCarrinho = data.itens.find((i) => i.produto_id === p.id);
                const semEstoque = p.saldo <= 0;
                return (
                  <div
                    key={p.id}
                    onClick={() => adicionar(p)}
                    className={`flex items-center gap-3 p-3 rounded-lg border transition-fast ${semEstoque ? "opacity-50 cursor-not-allowed" : "hover:bg-cream/30 cursor-pointer"}`}
                    style={{ borderColor: noCarrinho ? "rgba(201,40,31,0.25)" : "#E5E2DA", background: noCarrinho ? "rgba(201,40,31,0.04)" : undefined }}
                  >
                    <div className="w-10 h-10 rounded-md flex items-center justify-center text-xs font-bold flex-shrink-0" style={{ background: "#F0EDE5", color: "#777A78", fontFamily: "var(--font-condensed)" }}>
                      {p.nome.slice(0, 2).toUpperCase()}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="text-charcoal font-medium text-sm truncate">{p.nome}</div>
                      <div className="flex items-center gap-2 mt-0.5 text-xs">
                        <span style={{ fontFamily: "var(--font-mono)", color: "#777A78" }}>{p.codigo}</span>
                        <span className="text-gray-text">·</span>
                        <span style={{ color: semEstoque ? "#C9281F" : p.saldo <= p.estoque_minimo ? "#D4610D" : "#777A78" }}>
                          {semEstoque ? "Sem estoque" : `${p.saldo.toLocaleString("pt-BR")} ${p.unidade.toLowerCase()} em estoque`}
                        </span>
                      </div>
                    </div>
                    <div className="text-right flex-shrink-0">
                      <div className="text-charcoal font-bold" style={{ fontFamily: "var(--font-condensed)", fontSize: 18 }}>{brl(p.preco_venda)}</div>
                      {noCarrinho && <div className="text-xs text-red">✓ no carrinho ({noCarrinho.quantidade})</div>}
                    </div>
                  </div>
                );
              })}
              {resultados.length === 0 && <div className="py-8 text-center text-gray-text text-sm">Nenhum produto encontrado.</div>}
            </div>
          </Card>
        </div>

        {/* Carrinho */}
        <div className="w-80 flex-shrink-0">
          <Card className="sticky top-20">
            <div className="px-5 py-4" style={{ background: "#171918", borderRadius: "8px 8px 0 0" }}>
              <div className="text-white font-bold" style={{ fontFamily: "var(--font-condensed)", fontSize: 16, letterSpacing: "0.04em" }}>CARRINHO</div>
              <div style={{ color: "#777A78", fontSize: 12 }}>{data.itens.length} {data.itens.length === 1 ? "item" : "itens"}</div>
            </div>

            <div className="px-4 py-3 space-y-3" style={{ minHeight: 120 }}>
              {data.itens.length === 0 ? (
                <div className="py-6 text-center text-gray-text" style={{ fontSize: 13 }}>Adicione produtos à venda</div>
              ) : (
                data.itens.map((item) => {
                  const p = porId(item.produto_id);
                  return (
                    <div key={p.id} className="flex items-start gap-2">
                      <div className="flex-1 min-w-0">
                        <div className="text-charcoal font-medium" style={{ fontSize: 13 }}>{p.nome}</div>
                        <div className="text-gray-text" style={{ fontSize: 12, fontFamily: "var(--font-mono)" }}>{brl(p.preco_venda)} / {p.unidade.toLowerCase()}</div>
                      </div>
                      <div className="flex items-center gap-1.5 flex-shrink-0">
                        <button onClick={() => alterarQtd(p.id, -1)} className="w-6 h-6 rounded flex items-center justify-center text-gray-text hover:bg-cream" style={{ border: "1px solid #E5E2DA" }}><Minus size={10} /></button>
                        <span style={{ fontFamily: "var(--font-mono)", fontSize: 13, minWidth: 20, textAlign: "center" }}>{item.quantidade}</span>
                        <button onClick={() => alterarQtd(p.id, 1)} disabled={item.quantidade >= p.saldo} className="w-6 h-6 rounded flex items-center justify-center text-gray-text hover:bg-cream disabled:opacity-40" style={{ border: "1px solid #E5E2DA" }}><Plus size={10} /></button>
                      </div>
                      <div className="text-right min-w-[64px]" style={{ fontFamily: "var(--font-condensed)", fontWeight: 600, fontSize: 14 }}>{brl(p.preco_venda * item.quantidade)}</div>
                      <button onClick={() => remover(p.id)} className="text-gray-text hover:text-red mt-0.5"><Trash2 size={12} /></button>
                    </div>
                  );
                })
              )}
            </div>

            <div className="px-4 py-3 space-y-2" style={{ borderTop: "1px solid #F0EDE5" }}>
              <div className="flex justify-between text-sm">
                <span className="text-gray-text">Subtotal</span>
                <span style={{ fontFamily: "var(--font-mono)" }}>{brl(subtotal)}</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-text">Desconto (R$)</span>
                <input
                  placeholder="0,00"
                  value={data.desconto}
                  onChange={(e) => setData("desconto", e.target.value)}
                  className="w-20 text-right text-sm bg-transparent focus:outline-none border-b"
                  style={{ fontFamily: "var(--font-mono)", borderColor: "#E5E2DA", color: desconto > 0 ? "#C9281F" : "#777A78" }}
                />
              </div>
              <div className="flex justify-between items-center pt-2" style={{ borderTop: "2px solid #171918" }}>
                <span className="font-semibold text-charcoal">Total</span>
                <span className="text-charcoal" style={{ fontFamily: "var(--font-condensed)", fontWeight: 800, fontSize: 24 }}>{brl(total)}</span>
              </div>
            </div>

            <div className="px-4 py-3" style={{ borderTop: "1px solid #F0EDE5" }}>
              <div className="text-xs text-gray-text mb-2 uppercase font-medium" style={{ letterSpacing: "0.06em", fontSize: 10.5 }}>Forma de pagamento</div>
              <div className="grid grid-cols-2 gap-1.5">
                {Object.entries(formas).map(([id, label]) => (
                  <button
                    key={id}
                    onClick={() => setData("forma_pagamento", id)}
                    className={`flex items-center gap-2 px-3 py-2 rounded-md text-xs font-medium border transition-fast ${
                      data.forma_pagamento === id ? "text-red border-red/40 bg-red/5" : "text-gray-text border-gray-light hover:text-charcoal"
                    }`}
                  >
                    <span>{icones[id]}</span>{label}
                  </button>
                ))}
              </div>
            </div>

            {erroGeral && (
              <div className="mx-4 mb-3 flex gap-2 p-3 rounded-md text-xs" style={{ background: "rgba(201,40,31,0.06)", border: "1px solid rgba(201,40,31,0.2)", color: "#C9281F" }}>
                <AlertTriangle size={14} className="flex-shrink-0" /> {erroGeral}
              </div>
            )}

            <div className="px-4 pb-4">
              <button
                onClick={finalizar}
                disabled={data.itens.length === 0 || processing}
                className="w-full py-3.5 rounded-lg font-bold text-white transition-fast disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2 bg-red hover:bg-red-dark"
                style={{ fontFamily: "var(--font-condensed)", fontSize: 16, letterSpacing: "0.05em" }}
              >
                {processing ? "FINALIZANDO..." : "FINALIZAR VENDA"} <ChevronRight size={16} />
              </button>
            </div>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}