import { useState, type FormEvent } from "react";
import { useForm } from "@inertiajs/react";
import { Plus, Search, Camera, Pencil } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Select, Card, Table, Th, Td, StatusBadge, Modal, Badge } from "@/components/ui";
import FotoInput from "@/components/FotoInput";
import ProdutoForm, { type ProdutoEditavel } from "./ProdutoForm";

type Produto = ProdutoEditavel & { situacao: string };

type Props = { produtos: Produto[]; categorias: string[]; marcas: string[] };

/** Janela para tirar, trocar ou remover a foto de um produto já cadastrado. */
function FotoProduto({ produto, onClose }: { produto: Produto; onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm<{ foto: File | null; remover_foto: boolean }>({
    foto: null,
    remover_foto: false,
  });

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    post(`/produtos/${produto.id}/foto`, { forceFormData: true, preserveScroll: true, onSuccess: () => onClose() });
  };

  return (
    <form onSubmit={salvar} className="space-y-4">
      <FotoInput
        fotoAtual={data.remover_foto ? null : produto.foto_url}
        onChange={(f) => setData((d) => ({ ...d, foto: f, remover_foto: false }))}
        onRemover={() => setData((d) => ({ ...d, foto: null, remover_foto: true }))}
        erro={errors.foto}
      />
      <div className="flex justify-end gap-3 pt-3" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing || (!data.foto && !data.remover_foto)}>
          {processing ? "Salvando..." : "Salvar foto"}
        </Button>
      </div>
    </form>
  );
}

/** Miniatura: foto do produto ou as iniciais. Clicar abre a troca de foto. */
function Miniatura({ produto, onClick }: { produto: Produto; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={produto.foto_url ? "Trocar foto" : "Adicionar foto"}
      className="relative w-9 h-9 rounded-md flex items-center justify-center text-xs font-bold flex-shrink-0 overflow-hidden group"
      style={{ background: "#F0EDE5", color: "#777A78", fontFamily: "var(--font-condensed)" }}
    >
      {produto.foto_url ? (
        <img src={produto.foto_url} alt={produto.nome} className="w-full h-full object-cover" />
      ) : (
        produto.nome.slice(0, 2).toUpperCase()
      )}
      <span className="absolute inset-0 bg-black/50 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-fast">
        <Camera size={14} />
      </span>
    </button>
  );
}

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);
const qtd = (v: number) => v.toLocaleString("pt-BR", { maximumFractionDigits: 3 });
const margem = (p: Produto) => (p.preco_custo > 0 ? `${(((p.preco_venda - p.preco_custo) / p.preco_venda) * 100).toFixed(0)}%` : "—");

export default function ProdutosIndex({ produtos, categorias, marcas }: Props) {
  const [busca, setBusca] = useState("");
  const [categoria, setCategoria] = useState("");
  const [marca, setMarca] = useState("");
  const [situacao, setSituacao] = useState("");
  const [novo, setNovo] = useState(false);
  const [fotoDe, setFotoDe] = useState<Produto | null>(null);
  const [editando, setEditando] = useState<Produto | null>(null);

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
        <Button variant="primary" onClick={() => setNovo(true)}>
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
              <Th soDesktop>Código</Th>
              <Th soDesktop>Categoria</Th>
              <Th soDesktop className="text-right">Custo</Th>
              <Th className="text-right">Venda</Th>
              <Th soDesktop className="text-right">Margem</Th>
              <Th className="text-right">Estoque</Th>
              <Th soDesktop>Status</Th>
              <Th></Th>
            </tr>
          </thead>
          <tbody>
            {filtrados.map((p) => (
              <tr key={p.id} className={`hover:bg-cream/40 transition-fast ${p.ativo ? "" : "opacity-60"}`}>
                <Td>
                  <div className="flex items-center gap-3">
                    <Miniatura produto={p} onClick={() => setFotoDe(p)} />
                    <div>
                      <div className="text-charcoal font-medium" style={{ fontSize: 13.5 }}>
                        {p.nome}
                        {!p.ativo && <span className="ml-2"><Badge variant="neutral">Inativo</Badge></span>}
                      </div>
                      <div className="text-gray-text" style={{ fontSize: 11.5 }}>
                        {/* No celular, o código aparece junto da marca. */}
                        <span className="md:hidden" style={{ fontFamily: "var(--font-mono)" }}>{p.codigo} · </span>
                        {p.marca ?? "—"}
                      </div>
                      <div className="md:hidden mt-1"><StatusBadge status={p.situacao} /></div>
                    </div>
                  </div>
                </Td>
                <Td soDesktop><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{p.codigo}</span></Td>
                <Td soDesktop><span className="text-gray-text" style={{ fontSize: 13 }}>{p.categoria ?? "—"}</span></Td>
                <Td soDesktop className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 13 }}>{p.preco_custo > 0 ? brl(p.preco_custo) : "—"}</span></Td>
                <Td className="text-right"><span className="whitespace-nowrap" style={{ fontFamily: "var(--font-mono)", fontWeight: 500, fontSize: 13 }}>{brl(p.preco_venda)}</span></Td>
                <Td soDesktop className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#1A7C4E" }}>{margem(p)}</span></Td>
                <Td className="text-right">
                  <span className="whitespace-nowrap" style={{ fontFamily: "var(--font-mono)", fontWeight: 500, color: p.saldo === 0 ? "#C9281F" : "#171918" }}>
                    {qtd(p.saldo)} {p.unidade.toLowerCase()}
                  </span>
                </Td>
                <Td soDesktop><StatusBadge status={p.situacao} /></Td>
                <Td className="text-right">
                  <Button variant="ghost" size="sm" onClick={() => setEditando(p)}>
                    <Pencil size={12} /> <span className="hidden sm:inline">Editar</span>
                  </Button>
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {filtrados.length === 0 && (
          <div className="py-12 text-center text-gray-text" style={{ fontSize: 14 }}>Nenhum produto encontrado.</div>
        )}
      </Card>

      {novo && (
        <Modal title="Novo produto" onClose={() => setNovo(false)} width="max-w-2xl">
          <ProdutoForm onClose={() => setNovo(false)} />
        </Modal>
      )}

      {editando && (
        <Modal title={`Editar: ${editando.nome}`} onClose={() => setEditando(null)} width="max-w-2xl">
          <ProdutoForm produto={editando} onClose={() => setEditando(null)} />
        </Modal>
      )}

      {fotoDe && (
        <Modal title={`Foto: ${fotoDe.nome}`} onClose={() => setFotoDe(null)}>
          <FotoProduto produto={fotoDe} onClose={() => setFotoDe(null)} />
        </Modal>
      )}
    </AppLayout>
  );
}