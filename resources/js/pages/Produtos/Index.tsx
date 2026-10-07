import { useState } from "react";
import { Plus, Search } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Select, Card, Table, Th, Td, StatusBadge } from "@/components/ui";

type Produto = {
  id: number;
  codigo: string;
  nome: string;
  categoria: string | null;
  marca: string | null;
  unidade: string;
  preco_custo: number;
  preco_venda: number;
  saldo: number;
  estoque_minimo: number;
  situacao: string;
};

type Props = { produtos: Produto[]; categorias: string[]; marcas: string[] };

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);
const qtd = (v: number) => v.toLocaleString("pt-BR", { maximumFractionDigits: 3 });
const margem = (p: Produto) => (p.preco_custo > 0 ? `${(((p.preco_venda - p.preco_custo) / p.preco_venda) * 100).toFixed(0)}%` : "—");

export default function ProdutosIndex({ produtos, categorias, marcas }: Props) {
  const [busca, setBusca] = useState("");
  const [categoria, setCategoria] = useState("");
  const [marca, setMarca] = useState("");
  const [situacao, setSituacao] = useState("");

  const filtrados = produtos.filter((p) => {
    const q = busca.toLowerCase();
    return (
      (!q || p.nome.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)) &&
      (!categoria || p.categoria === categoria) &&
      (!marca || p.marca === marca) &&
      (!situacao || p.situacao === situacao)
    );
  });

  return (
    <AppLayout title="Produtos">
      <PageHeader title="Produtos" subtitle="Gerencie todas as peças e produtos da oficina.">
        <Button variant="primary">
          <Plus size={14} /> Novo produto
        </Button>
      </PageHeader>

      <div className="flex flex-wrap items-center gap-3 mb-5">
        <Input className="w-72" placeholder="Buscar produto ou código..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
        <Select value={categoria} onChange={setCategoria}>
          <option value="">Todas as categorias</option>
          {categorias.map((c) => <option key={c} value={c}>{c}</option>)}
        </Select>
        <Select value={marca} onChange={setMarca}>
          <option value="">Todas as marcas</option>
          {marcas.map((m) => <option key={m} value={m}>{m}</option>)}
        </Select>
        <Select value={situacao} onChange={setSituacao}>
          <option value="">Todos os status</option>
          {["Normal", "Baixo", "Crítico", "Sem estoque"].map((s) => <option key={s} value={s}>{s}</option>)}
        </Select>
        <span className="text-gray-text text-sm ml-auto">{filtrados.length} produtos</span>
      </div>

      <Card>
        <Table>
          <thead>
            <tr>
              <Th>Produto</Th>
              <Th>Código</Th>
              <Th>Categoria</Th>
              <Th className="text-right">Custo</Th>
              <Th className="text-right">Venda</Th>
              <Th className="text-right">Margem</Th>
              <Th className="text-right">Estoque</Th>
              <Th>Status</Th>
            </tr>
          </thead>
          <tbody>
            {filtrados.map((p) => (
              <tr key={p.id} className="hover:bg-cream/40 transition-fast">
                <Td>
                  <div className="flex items-center gap-3">
                    <div
                      className="w-9 h-9 rounded-md flex items-center justify-center text-xs font-bold flex-shrink-0"
                      style={{ background: "#F0EDE5", color: "#777A78", fontFamily: "var(--font-condensed)" }}
                    >
                      {p.nome.slice(0, 2).toUpperCase()}
                    </div>
                    <div>
                      <div className="text-charcoal font-medium" style={{ fontSize: 13.5 }}>{p.nome}</div>
                      <div className="text-gray-text" style={{ fontSize: 11.5 }}>{p.marca ?? "—"}</div>
                    </div>
                  </div>
                </Td>
                <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{p.codigo}</span></Td>
                <Td><span className="text-gray-text" style={{ fontSize: 13 }}>{p.categoria ?? "—"}</span></Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 13 }}>{p.preco_custo > 0 ? brl(p.preco_custo) : "—"}</span></Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontWeight: 500, fontSize: 13 }}>{brl(p.preco_venda)}</span></Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#1A7C4E" }}>{margem(p)}</span></Td>
                <Td className="text-right">
                  <span style={{ fontFamily: "var(--font-mono)", fontWeight: 500, color: p.saldo === 0 ? "#C9281F" : "#171918" }}>
                    {qtd(p.saldo)} {p.unidade.toLowerCase()}
                  </span>
                </Td>
                <Td><StatusBadge status={p.situacao} /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {filtrados.length === 0 && (
          <div className="py-12 text-center text-gray-text" style={{ fontSize: 14 }}>Nenhum produto encontrado.</div>
        )}
      </Card>
    </AppLayout>
  );
}