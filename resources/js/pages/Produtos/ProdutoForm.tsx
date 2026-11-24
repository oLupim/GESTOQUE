import { useForm } from "@inertiajs/react";
import { useRef, useState, type FormEvent, type ReactNode } from "react";
import { Button, Input, Select } from "@/components/ui";
import { FotoQuadrado } from "@/components/FotoInput";
import Autocompletar, { contem } from "@/components/Autocompletar";
import LeitorCodigoBarras from "@/components/LeitorCodigoBarras";

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

/** Categoria padrão do sistema: prefixo do código e sugestões para o cadastro. */
export type CategoriaPadrao = {
  nome: string; prefixo: string | null; unidade: string | null; ncm: string | null; cfop: string | null; proximo_codigo: string | null;
};

/** Listas usadas nas sugestões do formulário. */
export type SugestoesProduto = {
  categoriasPadrao: CategoriaPadrao[];
  categorias: string[];
  marcas: string[];
  produtos: ProdutoEditavel[];
};

const igual = (a: string, b: string) => a.trim().toLowerCase() === b.trim().toLowerCase();

export default function ProdutoForm({ produto, onClose, sugestoes }: { produto?: ProdutoEditavel; onClose: () => void; sugestoes: SugestoesProduto }) {
  // Código preenchido pela categoria: se a categoria mudar, ele acompanha. Digitar no campo desliga isso.
  const [codigoAuto, setCodigoAuto] = useState(false);
  // NCM/CFOP vieram da categoria: mostra o aviso para conferir com o contador.
  const [fiscalSugerido, setFiscalSugerido] = useState(false);
  // Última categoria aplicada: um campo só é trocado se estiver vazio ou ainda com a sugestão anterior.
  const anterior = useRef<CategoriaPadrao | null>(null);

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

  /**
   * Aplica as sugestões da categoria padrão sobre os dados `d`.
   * Código: só no cadastro novo. Unidade/NCM/CFOP: só se o campo estiver vazio ou com a sugestão da categoria anterior.
   */
  const comCategoria = (d: Dados, nome: string, auto = codigoAuto): Dados => {
    const padrao = sugestoes.categoriasPadrao.find((c) => igual(c.nome, nome)) ?? null;
    const antes = anterior.current;
    const pode = (atual: string, sugeridoAntes: string | null | undefined) => !atual || atual === (sugeridoAntes ?? "");
    const novo = { ...d, categoria: padrao ? padrao.nome : nome };

    if (padrao) {
      if (!produto && padrao.proximo_codigo && (!d.codigo || auto)) {
        novo.codigo = padrao.proximo_codigo;
        setCodigoAuto(true);
      }
      if (padrao.unidade && (d.unidade === "UN" || d.unidade === antes?.unidade)) novo.unidade = padrao.unidade;
      if (padrao.ncm && pode(d.ncm, antes?.ncm)) { novo.ncm = padrao.ncm; setFiscalSugerido(true); }
      if (padrao.cfop && pode(d.cfop, antes?.cfop)) novo.cfop = padrao.cfop;
    } else if (!produto && auto) {
      // Saiu de uma categoria padrão: o código automático deixa de valer.
      novo.codigo = "";
      setCodigoAuto(false);
    }
    anterior.current = padrao;
    return novo;
  };

  const escolherCategoria = (nome: string) => setData((d) => comCategoria(d, nome));

  /** Copia os dados de um produto parecido (menos nome, códigos e foto) para cadastrar mais rápido. */
  const copiarDe = (p: ProdutoEditavel) =>
    setData((d) => comCategoria({
      ...d,
      marca: p.marca ?? "",
      unidade: p.unidade,
      preco_custo: paraCampo(p.preco_custo),
      preco_venda: paraCampo(p.preco_venda),
      estoque_minimo: paraCampo(p.estoque_minimo, 0),
      ncm: p.ncm ?? "",
      cfop: p.cfop ?? d.cfop,
    }, p.categoria ?? ""));

  // Listas filtradas pelo que já foi digitado.
  const nomesCategorias = [...new Set([...sugestoes.categoriasPadrao.map((c) => c.nome), ...sugestoes.categorias])];
  const categoriasFiltradas = nomesCategorias.filter((c) => !igual(c, data.categoria) && contem(c, data.categoria));
  const marcasFiltradas = sugestoes.marcas.filter((m) => !igual(m, data.marca) && contem(m, data.marca));
  const parecidos = !produto && data.nome.trim().length >= 2
    ? sugestoes.produtos.filter((p) => data.nome.trim().split(/\s+/).every((palavra) => contem(p.nome, palavra)))
    : [];
  const categoriaAtual = sugestoes.categoriasPadrao.find((c) => igual(c.nome, data.categoria));

  // Outro produto com o mesmo código de barras (o servidor também bloqueia, mas aqui avisa antes de preencher tudo).
  const eanDuplicado = data.codigo_barras
    ? sugestoes.produtos.find((p) => p.codigo_barras === data.codigo_barras.replace(/\D/g, "") && p.id !== produto?.id)
    : undefined;
  const avisoEan = eanDuplicado && (
    <p className="text-xs mt-1" style={{ color: "#D4610D" }}>
      Já cadastrado: {eanDuplicado.nome} ({eanDuplicado.codigo}). Para mudar o estoque dele, use Estoque → Movimentar.
    </p>
  );

  return (
    <form onSubmit={salvar} className="space-y-5">
      {/* Dois quadrados, cada um com uma função só: ler o código de barras (preenche o EAN) e a foto do catálogo. */}
      <div>
        <div className="grid grid-cols-2 gap-3">
          <LeitorCodigoBarras quadrado lido={data.codigo_barras || undefined} onLido={(codigo) => setData("codigo_barras", codigo)} />
          <FotoQuadrado
            fotoAtual={data.remover_foto ? null : produto?.foto_url}
            onChange={(f) => setData((d) => ({ ...d, foto: f, remover_foto: false }))}
            onRemover={produto ? () => setData((d) => ({ ...d, foto: null, remover_foto: true })) : undefined}
            erro={errors.foto}
          />
        </div>
        {avisoEan}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Campo label="Nome do produto *" erro={errors.nome} className="sm:col-span-2">
          {/* Cadastro novo: sugere produtos parecidos; tocar copia categoria, marca, preços e fiscal. */}
          <Autocompletar
            value={data.nome}
            onChange={(v) => setData("nome", v)}
            placeholder="Ex: Óleo Motul 10W40"
            sugestoes={parecidos}
            chave={(p) => p.id}
            onEscolher={copiarDe}
            rodape="Toque para copiar categoria, marca, preços e fiscal. O nome continua o que você digitou."
            renderItem={(p) => (
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-charcoal text-sm font-medium truncate">{p.nome}</div>
                  <div className="text-gray-text text-xs truncate">{[p.codigo, p.categoria, p.marca].filter(Boolean).join(" · ")}</div>
                </div>
                <span className="text-red text-xs font-medium flex-shrink-0">Copiar dados</span>
              </div>
            )}
          />
        </Campo>
        <Campo label="Categoria" erro={errors.categoria}>
          <Autocompletar
            value={data.categoria}
            onChange={escolherCategoria}
            placeholder="Toque para ver as categorias"
            sugestoes={data.categoria ? categoriasFiltradas : nomesCategorias}
            chave={(c) => c}
            onEscolher={escolherCategoria}
            renderItem={(c) => {
              const padrao = sugestoes.categoriasPadrao.find((x) => x.nome === c);
              return (
                <div className="flex items-center justify-between gap-3 text-sm">
                  <span className="text-charcoal">{c}</span>
                  {padrao?.prefixo && <span className="text-gray-text text-xs" style={{ fontFamily: "var(--font-mono)" }}>{padrao.prefixo}</span>}
                </div>
              );
            }}
          />
        </Campo>
        <Campo label="Marca" erro={errors.marca}>
          {/* Sugere as marcas já usadas, para não criar "honda" e "Honda" separadas. */}
          <Autocompletar
            value={data.marca}
            onChange={(v) => setData("marca", v)}
            placeholder="Ex: Motul"
            sugestoes={data.marca ? marcasFiltradas : []}
            chave={(m) => m}
            onEscolher={(m) => setData("marca", m)}
            renderItem={(m) => <span className="text-charcoal text-sm">{m}</span>}
          />
        </Campo>
        <Campo label="Código interno *" erro={errors.codigo}>
          <Input
            placeholder={!produto && categoriaAtual?.prefixo ? "Automático" : "OLE-001"}
            value={data.codigo}
            onChange={(v) => { setData("codigo", v); setCodigoAuto(false); }}
          />
          {codigoAuto && !produto && <p className="text-gray-text text-xs mt-1">Próximo código da categoria. Pode trocar se quiser.</p>}
        </Campo>
        <Campo label="Código de barras" erro={errors.codigo_barras}>
          <Input placeholder="EAN (ou use Escanear, no topo)" value={data.codigo_barras} onChange={(v) => setData("codigo_barras", v)} />
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
      {fiscalSugerido && (
        <p className="text-xs -mt-2" style={{ color: "#D4610D" }}>NCM sugerido pela categoria. Confira com o contador antes de emitir nota.</p>
      )}

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