import { useForm } from "@inertiajs/react";
import type { FormEvent, ReactNode } from "react";
import { Button, Input, Select } from "@/components/ui";
import FotoInput from "@/components/FotoInput";

const UNIDADES = ["UN", "PAR", "KIT", "JG", "L", "ML", "KG", "M", "FR", "CX"];

function Campo({ label, erro, children, className = "" }: { label: string; erro?: string; children: ReactNode; className?: string }) {
  return (
    <div className={className}>
      <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>
        {label}
      </label>
      {children}
      {erro && <p className="text-red text-xs mt-1">{erro}</p>}
    </div>
  );
}

function Secao({ titulo }: { titulo: string }) {
  return (
    <div className="pt-2 text-xs font-semibold text-gray-text uppercase" style={{ borderTop: "1px solid #F0EDE5", letterSpacing: "0.07em" }}>
      {titulo}
    </div>
  );
}

/** Dados de um produto já cadastrado, para abrir o formulário em modo de edição. */
export type ProdutoEditavel = {
  id: number;
  nome: string; categoria: string | null; marca: string | null; codigo: string; codigo_barras: string | null; unidade: string;
  preco_custo: number; preco_venda: number; estoque_minimo: number; saldo: number; ncm: string | null; cfop: string | null;
  ativo: boolean; foto_url: string | null;
};

// 65.5 → "65,50" (como a pessoa digita no Brasil); zero vira campo vazio.
const paraCampo = (v: number, casas = 2) => (v ? v.toFixed(casas).replace(".", ",") : "");

type Dados = {
  _method?: string;
  foto: File | null; remover_foto: boolean; ativo: boolean;
  nome: string; categoria: string; marca: string; codigo: string; codigo_barras: string; unidade: string;
  preco_custo: string; preco_venda: string; estoque_inicial: string; estoque_minimo: string; ncm: string; cfop: string;
};

export default function ProdutoForm({ produto, onClose }: { produto?: ProdutoEditavel; onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm<Dados>({
    // Edição vai por POST com _method=put: assim a foto (arquivo) chega ao servidor.
    ...(produto ? { _method: "put" } : {}),
    foto: null,
    remover_foto: false,
    ativo: produto?.ativo ?? true,
    nome: produto?.nome ?? "",
    categoria: produto?.categoria ?? "",
    marca: produto?.marca ?? "",
    codigo: produto?.codigo ?? "",
    codigo_barras: produto?.codigo_barras ?? "",
    unidade: produto?.unidade ?? "UN",
    preco_custo: produto ? paraCampo(produto.preco_custo) : "",
    preco_venda: produto ? paraCampo(produto.preco_venda) : "",
    estoque_inicial: "",
    estoque_minimo: produto ? paraCampo(produto.estoque_minimo, 0) : "",
    ncm: produto?.ncm ?? "",
    cfop: produto?.cfop ?? "5102",
  });

  const custo = parseFloat(data.preco_custo.replace(",", "."));
  const venda = parseFloat(data.preco_venda.replace(",", "."));
  const margem = custo > 0 && venda > 0 ? (((venda - custo) / venda) * 100).toFixed(1) : null;

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    post(produto ? `/produtos/${produto.id}` : "/produtos", { forceFormData: true, preserveScroll: true, onSuccess: () => onClose() });
  };

  return (
    <form onSubmit={salvar} className="space-y-5">
      <Campo label="Foto (opcional)" erro={errors.foto}>
        <FotoInput
          fotoAtual={data.remover_foto ? null : produto?.foto_url}
          onChange={(f) => setData((d) => ({ ...d, foto: f, remover_foto: false }))}
          onRemover={produto ? () => setData((d) => ({ ...d, foto: null, remover_foto: true })) : undefined}
        />
      </Campo>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Campo label="Nome do produto *" erro={errors.nome} className="sm:col-span-2">
          <Input placeholder="Ex: Óleo Motul 10W40" value={data.nome} onChange={(v) => setData("nome", v)} />
        </Campo>
        <Campo label="Categoria" erro={errors.categoria}>
          <Input placeholder="Ex: Lubrificantes" value={data.categoria} onChange={(v) => setData("categoria", v)} />
        </Campo>
        <Campo label="Marca" erro={errors.marca}>
          <Input placeholder="Ex: Motul" value={data.marca} onChange={(v) => setData("marca", v)} />
        </Campo>
        <Campo label="Código interno *" erro={errors.codigo}>
          <Input placeholder="OLE-001" value={data.codigo} onChange={(v) => setData("codigo", v)} />
        </Campo>
        <Campo label="Código de barras" erro={errors.codigo_barras}>
          <Input placeholder="EAN" value={data.codigo_barras} onChange={(v) => setData("codigo_barras", v)} />
        </Campo>
      </div>

      <Secao titulo="Valores" />
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Campo label="Custo (R$)" erro={errors.preco_custo}>
          <Input placeholder="0,00" value={data.preco_custo} onChange={(v) => setData("preco_custo", v)} />
        </Campo>
        <Campo label="Venda (R$) *" erro={errors.preco_venda}>
          <Input placeholder="0,00" value={data.preco_venda} onChange={(v) => setData("preco_venda", v)} />
        </Campo>
        <Campo label="Margem">
          <div
            className="px-3 py-2 rounded-md border text-sm"
            style={{ borderColor: "#D8D4CC", background: "#F9F7F3", fontFamily: "var(--font-mono)", color: margem ? "#1A7C4E" : "#777A78" }}
          >
            {margem ? `${margem}%` : "—"}
          </div>
        </Campo>
      </div>

      <Secao titulo="Estoque" />
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {produto ? (
          // Na edição o estoque não muda: só por movimentação (rastreabilidade).
          <Campo label="Estoque atual">
            <div className="px-3 py-2 rounded-md border text-sm" style={{ borderColor: "#D8D4CC", background: "#F9F7F3", fontFamily: "var(--font-mono)" }}>
              {produto.saldo.toLocaleString("pt-BR", { maximumFractionDigits: 3 })} {produto.unidade.toLowerCase()}
            </div>
            <p className="text-gray-text text-xs mt-1">Para mudar, use Estoque → Movimentar.</p>
          </Campo>
        ) : (
          <Campo label="Qtd. inicial" erro={errors.estoque_inicial}>
            <Input placeholder="0" value={data.estoque_inicial} onChange={(v) => setData("estoque_inicial", v)} />
          </Campo>
        )}
        <Campo label="Estoque mínimo" erro={errors.estoque_minimo}>
          <Input placeholder="0" value={data.estoque_minimo} onChange={(v) => setData("estoque_minimo", v)} />
        </Campo>
        <Campo label="Unidade *" erro={errors.unidade}>
          <Select value={data.unidade} onChange={(v) => setData("unidade", v)} className="w-full">
            {UNIDADES.map((u) => <option key={u} value={u}>{u}</option>)}
          </Select>
        </Campo>
      </div>

      <Secao titulo="Fiscal (NF-e)" />
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Campo label="NCM" erro={errors.ncm}>
          <Input placeholder="0000.00.00" value={data.ncm} onChange={(v) => setData("ncm", v)} />
        </Campo>
        <Campo label="CFOP" erro={errors.cfop}>
          <Input placeholder="5102" value={data.cfop} onChange={(v) => setData("cfop", v)} />
        </Campo>
      </div>

      {produto && (
        <label className="flex items-start gap-2 text-sm text-charcoal">
          <input type="checkbox" className="mt-0.5 accent-[#C9281F]" checked={data.ativo} onChange={(e) => setData("ativo", e.target.checked)} />
          <span>
            Produto ativo
            <span className="block text-gray-text text-xs">Desmarque para tirar do catálogo e das vendas. O histórico continua guardado.</span>
          </span>
        </label>
      )}

      <div className="flex items-center justify-end gap-3 pt-3" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing}>
          {processing ? "Salvando..." : produto ? "Salvar alterações" : "Salvar produto"}
        </Button>
      </div>
    </form>
  );
}