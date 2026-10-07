import { useState } from "react";
import { Head } from "@inertiajs/react";
import { Search, MessageCircle, MapPin } from "lucide-react";

type Produto = { id: number; nome: string; marca: string | null; categoria: string | null; preco: number; ultimas: boolean };
type Props = {
  produtos: Produto[];
  categorias: string[];
  oficina: { nome: string; whatsapp: string | null; endereco: string | null };
};

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);

function linkWhatsapp(numero: string | null, texto: string) {
  return numero ? `https://wa.me/${numero}?text=${encodeURIComponent(texto)}` : null;
}

export default function Catalogo({ produtos, categorias, oficina }: Props) {
  const [busca, setBusca] = useState("");
  const [categoria, setCategoria] = useState("");

  const filtrados = produtos.filter((p) => {
    const q = busca.trim().toLowerCase();
    return (
      (!q || p.nome.toLowerCase().includes(q) || (p.marca ?? "").toLowerCase().includes(q)) &&
      (!categoria || p.categoria === categoria)
    );
  });

  const contato = linkWhatsapp(oficina.whatsapp, `Olá! Vi o catálogo da ${oficina.nome} e gostaria de uma informação.`);

  return (
    <>
      <Head title="Peças disponíveis" />
      <div className="min-h-screen bg-cream">
        <header className="bg-charcoal">
          <div className="max-w-6xl mx-auto px-5 py-6 flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded flex items-center justify-center text-white font-bold bg-red" style={{ fontFamily: "var(--font-condensed)", fontSize: 18 }}>
                {oficina.nome.charAt(0)}
              </div>
              <div>
                <div className="text-white leading-none" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 22, letterSpacing: "0.04em" }}>
                  {oficina.nome.toUpperCase()}
                </div>
                {oficina.endereco && (
                  <div className="flex items-center gap-1 mt-1" style={{ color: "#9A9D9B", fontSize: 12 }}>
                    <MapPin size={11} /> {oficina.endereco}
                  </div>
                )}
              </div>
            </div>
            {contato && (
              <a href={contato} target="_blank" rel="noreferrer" className="flex items-center gap-2 px-4 py-2 rounded-md text-white text-sm font-medium" style={{ background: "#1A7C4E" }}>
                <MessageCircle size={15} /> Falar no WhatsApp
              </a>
            )}
          </div>
        </header>

        <main className="max-w-6xl mx-auto px-5 py-8">
          <h1 className="text-charcoal" style={{ fontFamily: "var(--font-condensed)", fontWeight: 800, fontSize: 30 }}>Peças disponíveis</h1>
          <p className="text-gray-text mb-6" style={{ fontSize: 14 }}>Estoque atualizado em tempo real. Consulte e chame no WhatsApp para reservar.</p>

          <div className="flex flex-wrap gap-3 mb-6">
            <div className="relative flex-1 min-w-[240px]">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-text" />
              <input
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                placeholder="Buscar peça ou marca..."
                className="w-full bg-white border rounded-md pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-red"
                style={{ borderColor: "#D8D4CC" }}
              />
            </div>
            <div className="flex flex-wrap gap-2">
              {["", ...categorias].map((c) => (
                <button
                  key={c || "todas"}
                  onClick={() => setCategoria(c)}
                  className={`px-3 py-2 rounded-md text-sm border transition-fast ${categoria === c ? "bg-charcoal text-white border-charcoal" : "bg-white text-gray-text hover:text-charcoal"}`}
                  style={{ borderColor: categoria === c ? undefined : "#D8D4CC" }}
                >
                  {c || "Todas"}
                </button>
              ))}
            </div>
          </div>

          <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))" }}>
            {filtrados.map((p) => {
              const pedir = linkWhatsapp(oficina.whatsapp, `Olá! Tenho interesse em: ${p.nome}${p.marca ? ` (${p.marca})` : ""} — ${brl(p.preco)}. Ainda está disponível?`);
              return (
                <div key={p.id} className="bg-white rounded-lg p-4 flex flex-col" style={{ border: "1px solid #E5E2DA" }}>
                  <div className="h-24 rounded-md mb-3 flex items-center justify-center" style={{ background: "#F0EDE5", color: "#9A9D9B", fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 28 }}>
                    {p.nome.slice(0, 2).toUpperCase()}
                  </div>
                  <div className="text-gray-text uppercase" style={{ fontSize: 10.5, letterSpacing: "0.06em" }}>{p.categoria ?? "Peças"}</div>
                  <div className="text-charcoal font-medium mt-0.5" style={{ fontSize: 14 }}>{p.nome}</div>
                  {p.marca && <div className="text-gray-text" style={{ fontSize: 12 }}>{p.marca}</div>}
                  <div className="flex-1" />
                  <div className="flex items-end justify-between mt-3">
                    <div className="text-charcoal" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 22 }}>{brl(p.preco)}</div>
                    <span className="text-xs font-medium" style={{ color: p.ultimas ? "#D4610D" : "#1A7C4E" }}>
                      {p.ultimas ? "Últimas unidades" : "Disponível"}
                    </span>
                  </div>
                  {pedir && (
                    <a href={pedir} target="_blank" rel="noreferrer" className="mt-3 flex items-center justify-center gap-1.5 py-2 rounded-md text-white text-sm font-medium bg-red hover:bg-red-dark transition-fast">
                      <MessageCircle size={13} /> Pedir pelo WhatsApp
                    </a>
                  )}
                </div>
              );
            })}
          </div>

          {filtrados.length === 0 && <div className="py-16 text-center text-gray-text">Nenhuma peça encontrada.</div>}
        </main>
      </div>
    </>
  );
}