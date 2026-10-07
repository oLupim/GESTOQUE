import { useForm } from "@inertiajs/react";
import type { FormEvent, ReactNode } from "react";
import { Button, Input, Select } from "@/components/ui";

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

export default function ProdutoForm({ onClose }: { onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm({
    nome: "",
    categoria: "",
    marca: "",
    codigo: "",
    codigo_barras: "",
    unidade: "UN",
    preco_custo: "",
    preco_venda: "",
    estoque_inicial: "",
    estoque_minimo: "",
    ncm: "",
    cfop: "5102",
  });

  const custo = parseFloat(data.preco_custo.replace(",", "."));
  const venda = parseFloat(data.preco_venda.replace(",", "."));
  const margem = custo > 0 && venda > 0 ? (((venda - custo) / venda) * 100).toFixed(1) : null;

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    post("/produtos", { preserveScroll: true, onSuccess: () => onClose() });
  };

  return (
    <form onSubmit={salvar} className="space-y-5">
      <div className="grid grid-cols-2 gap-4">
        <Campo label="Nome do produto *" erro={errors.nome} className="col-span-2">
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
      <div className="grid grid-cols-3 gap-4">
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
      <div className="grid grid-cols-3 gap-4">
        <Campo label="Qtd. inicial" erro={errors.estoque_inicial}>
          <Input placeholder="0" value={data.estoque_inicial} onChange={(v) => setData("estoque_inicial", v)} />
        </Campo>
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
      <div className="grid grid-cols-2 gap-4">
        <Campo label="NCM" erro={errors.ncm}>
          <Input placeholder="0000.00.00" value={data.ncm} onChange={(v) => setData("ncm", v)} />
        </Campo>
        <Campo label="CFOP" erro={errors.cfop}>
          <Input placeholder="5102" value={data.cfop} onChange={(v) => setData("cfop", v)} />
        </Campo>
      </div>

      <div className="flex items-center justify-end gap-3 pt-3" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing}>
          {processing ? "Salvando..." : "Salvar produto"}
        </Button>
      </div>
    </form>
  );
}