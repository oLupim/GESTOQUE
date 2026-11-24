import { useEffect, useState } from "react";
import { Camera, Images, ImageIcon, Trash2 } from "lucide-react";
import { reduzirImagem } from "@/lib/imagem";

/**
 * Campo de foto com dois botões:
 * - "Tirar foto" (vermelho): no celular abre direto a câmera;
 * - "Galeria": escolhe uma foto já salva.
 * A foto é reduzida no próprio navegador antes de enviar.
 */
/**
 * Quadrado "Foto do produto" (a que aparece no catálogo), para ficar ao lado do "Escanear código".
 * Sem `capture`: no iPhone, tocar abre o menu do sistema com "Tirar foto" e "Fototeca".
 */
export function FotoQuadrado({ fotoAtual, onChange, onRemover, erro, maxLado = 1024 }: {
  fotoAtual?: string | null;
  onChange: (arquivo: File | null) => void;
  onRemover?: () => void;
  erro?: string;
  maxLado?: number;
}) {
  const [previa, setPrevia] = useState<string | null>(null);
  const [preparando, setPreparando] = useState(false);
  const [chave, setChave] = useState(0);

  useEffect(() => () => { if (previa) URL.revokeObjectURL(previa); }, [previa]);

  const escolher = async (arquivo: File | undefined) => {
    if (!arquivo) return;
    setPreparando(true);
    try {
      const reduzida = await reduzirImagem(arquivo, { maxLado });
      setPrevia(URL.createObjectURL(reduzida));
      onChange(reduzida);
    } finally {
      setPreparando(false);
      setChave((c) => c + 1);
    }
  };

  const limpar = () => {
    setPrevia(null);
    onChange(null);
    onRemover?.();
  };

  const mostrar = previa ?? fotoAtual ?? null;

  return (
    <div>
      <label
        className={`relative w-full aspect-square sm:aspect-auto sm:h-36 flex flex-col items-center justify-center gap-1.5 p-3 rounded-lg border-2 border-dashed bg-white hover:bg-cream transition-fast text-center cursor-pointer overflow-hidden ${preparando ? "opacity-50 pointer-events-none" : ""}`}
        style={{ borderColor: mostrar ? "#1A7C4E" : "#D8D4CC" }}
      >
        {mostrar ? (
          <>
            <img src={mostrar} alt="Foto do produto" className="absolute inset-0 w-full h-full object-cover" />
            <span className="absolute bottom-0 inset-x-0 py-1.5 bg-black/60 text-white text-xs font-medium">Trocar foto do produto</span>
          </>
        ) : (
          <>
            <Camera size={30} style={{ color: "#C9281F" }} />
            <span className="text-charcoal text-sm font-semibold leading-tight">{preparando ? "Preparando…" : "Foto do produto"}</span>
            <span className="text-gray-text text-xs leading-tight">Aparece no catálogo</span>
          </>
        )}
        <input key={chave} type="file" accept="image/*" className="hidden" onChange={(e) => escolher(e.target.files?.[0])} />
      </label>
      {mostrar && !preparando && (
        <button type="button" onClick={limpar} className="inline-flex items-center gap-1.5 text-xs text-gray-text hover:text-red mt-1.5">
          <Trash2 size={12} /> Remover foto
        </button>
      )}
      {erro && <p className="text-red text-xs mt-1">{erro}</p>}
    </div>
  );
}

export default function FotoInput({
  fotoAtual,
  onChange,
  onRemover,
  erro,
  maxLado = 1024,
}: {
  /** URL da foto já salva (mostrada enquanto não escolher outra). */
  fotoAtual?: string | null;
  /** Recebe a foto já reduzida (ou null ao limpar). */
  onChange: (arquivo: File | null) => void;
  /** Se informado, mostra "Remover" para a foto já salva. */
  onRemover?: () => void;
  erro?: string;
  maxLado?: number;
}) {
  const [previa, setPrevia] = useState<string | null>(null);
  const [preparando, setPreparando] = useState(false);
  // Muda a key dos inputs para permitir escolher o mesmo arquivo de novo.
  const [chave, setChave] = useState(0);

  useEffect(() => () => { if (previa) URL.revokeObjectURL(previa); }, [previa]);

  const escolher = async (arquivo: File | undefined) => {
    if (!arquivo) return;
    setPreparando(true);
    try {
      const reduzida = await reduzirImagem(arquivo, { maxLado });
      setPrevia(URL.createObjectURL(reduzida));
      onChange(reduzida);
    } finally {
      setPreparando(false);
      setChave((c) => c + 1);
    }
  };

  const limpar = () => {
    setPrevia(null);
    onChange(null);
    onRemover?.();
  };

  const mostrar = previa ?? fotoAtual ?? null;
  const botao = "inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md text-sm font-medium cursor-pointer transition-fast";

  return (
    <div>
      <div className="flex items-center gap-4">
        <div
          className="w-28 h-28 rounded-lg flex items-center justify-center overflow-hidden flex-shrink-0"
          style={{ background: "#F0EDE5", border: "1px solid #E5E2DA" }}
        >
          {mostrar ? (
            <img src={mostrar} alt="Foto do produto" className="w-full h-full object-cover" />
          ) : (
            <ImageIcon size={28} style={{ color: "#9A9D9B" }} />
          )}
        </div>

        <div className="flex flex-col gap-2 flex-1 min-w-0">
          {/* capture="environment" = câmera traseira do celular. No computador abre a escolha de arquivo. */}
          <label className={`${botao} bg-red text-white hover:bg-red-dark ${preparando ? "opacity-50 pointer-events-none" : ""}`}>
            <Camera size={16} /> Tirar foto
            <input
              key={`camera-${chave}`}
              type="file"
              accept="image/*"
              capture="environment"
              className="hidden"
              onChange={(e) => escolher(e.target.files?.[0])}
            />
          </label>
          <label
            className={`${botao} bg-white text-charcoal border hover:bg-cream ${preparando ? "opacity-50 pointer-events-none" : ""}`}
            style={{ borderColor: "#E5E2DA" }}
          >
            <Images size={16} /> Escolher da galeria
            <input key={`galeria-${chave}`} type="file" accept="image/*" className="hidden" onChange={(e) => escolher(e.target.files?.[0])} />
          </label>
          {mostrar && !preparando && (
            <button type="button" onClick={limpar} className="inline-flex items-center gap-1.5 text-xs text-gray-text hover:text-red self-start">
              <Trash2 size={12} /> Remover foto
            </button>
          )}
        </div>
      </div>
      {preparando && <p className="text-gray-text text-xs mt-2">Preparando foto…</p>}
      {erro && <p className="text-red text-xs mt-2">{erro}</p>}
    </div>
  );
}
