import { useEffect, useRef, useState } from "react";
import { ImagePlus, ScanBarcode } from "lucide-react";
import { Modal } from "@/components/ui";
import { reduzirImagem } from "@/lib/imagem";

/**
 * Escaneia o código de barras (EAN/GTIN) pelo celular, de graça e sem enviar nada para fora.
 * Um botão só ("Escanear"): abre a câmera ao vivo; se ela não abrir, a mesma janela oferece ler de uma foto do código.
 * A biblioteca (ZXing, código aberto) só é baixada quando a pessoa toca no botão.
 */

// Só os formatos de produto: deixa a leitura mais rápida e evita ler outra coisa.
async function criarLeitor() {
  const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
    import("@zxing/browser"),
    import("@zxing/library"),
  ]);
  const dicas = new Map();
  dicas.set(DecodeHintType.POSSIBLE_FORMATS, [
    BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E, BarcodeFormat.CODE_128,
  ]);
  dicas.set(DecodeHintType.TRY_HARDER, true);
  return new BrowserMultiFormatReader(dicas);
}

function JanelaLeitura({ onLido, onFechar }: { onLido: (codigo: string) => void; onFechar: () => void }) {
  const video = useRef<HTMLVideoElement>(null);
  const [erroCamera, setErroCamera] = useState<string | null>(null);
  const [lendoFoto, setLendoFoto] = useState(false);
  const [avisoFoto, setAvisoFoto] = useState<string | null>(null);
  const [chave, setChave] = useState(0);
  // Guardado em ref: assim a câmera abre uma vez só, mesmo que a tela redesenhe.
  const aoLer = useRef(onLido);
  aoLer.current = onLido;

  useEffect(() => {
    let parar: (() => void) | null = null;
    let ativo = true;

    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      setErroCamera("A câmera ao vivo só funciona no site com HTTPS. Use uma foto do código.");
      return;
    }

    criarLeitor()
      .then((leitor) => leitor.decodeFromConstraints(
        { video: { facingMode: "environment" } }, // câmera traseira
        video.current!,
        (resultado, _erro, controles) => {
          if (resultado && ativo) {
            ativo = false;
            controles.stop();
            navigator.vibrate?.(80);
            aoLer.current(resultado.getText());
          }
        },
      ))
      .then((controles) => {
        if (ativo) parar = () => controles.stop();
        else controles.stop();
      })
      .catch((e: Error) => {
        setErroCamera(e?.name === "NotAllowedError"
          ? "O acesso à câmera foi negado. Libere nas configurações do navegador ou use uma foto do código."
          : "Não foi possível abrir a câmera. Use uma foto do código.");
      });

    // Fecha a câmera ao sair da janela (senão a luz da câmera fica acesa).
    return () => { ativo = false; parar?.(); };
  }, []);

  const lerFoto = async (arquivo: File | undefined) => {
    if (!arquivo) return;
    setLendoFoto(true);
    setAvisoFoto(null);
    let url: string | null = null;
    try {
      // Foto do iPhone é enorme: reduz antes, a leitura fica bem mais rápida.
      const menor = await reduzirImagem(arquivo, { maxLado: 1600, qualidade: 0.92 });
      url = URL.createObjectURL(menor);
      const leitor = await criarLeitor();
      aoLer.current((await leitor.decodeFromImageUrl(url)).getText());
    } catch {
      setAvisoFoto("Não achei o código na foto. Chegue mais perto, com as barras retas e bem iluminadas.");
    } finally {
      if (url) URL.revokeObjectURL(url);
      setLendoFoto(false);
      setChave((c) => c + 1); // permite escolher a mesma foto de novo
    }
  };

  return (
    <Modal title="Escanear código do produto" onClose={onFechar}>
      {erroCamera ? (
        <p className="text-sm text-red mb-4">{erroCamera}</p>
      ) : (
        <>
          <div className="relative rounded-lg overflow-hidden bg-black aspect-[4/3]">
            <video ref={video} className="w-full h-full object-cover" muted playsInline />
            {/* Faixa de mira: ajuda a pessoa a enquadrar as barras. */}
            <div className="absolute inset-x-6 top-1/2 -translate-y-1/2 h-24 rounded-md pointer-events-none" style={{ border: "2px solid rgba(255,255,255,0.85)", boxShadow: "0 0 0 9999px rgba(0,0,0,0.35)" }} />
          </div>
          <p className="text-gray-text text-sm mt-3 mb-4">Aponte para as barras e segure firme. A leitura é automática.</p>
        </>
      )}

      {/* Alternativa: tirar/escolher uma foto do código de barras (não é a foto do produto). */}
      <label
        className={`w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md border bg-white text-charcoal hover:bg-cream text-sm font-medium cursor-pointer ${lendoFoto ? "opacity-50 pointer-events-none" : ""}`}
        style={{ borderColor: "#D8D4CC" }}
      >
        <ImagePlus size={16} /> {lendoFoto ? "Procurando o código…" : "Usar uma foto do código"}
        <input key={chave} type="file" accept="image/*" className="hidden" onChange={(e) => lerFoto(e.target.files?.[0])} />
      </label>
      {avisoFoto && <p className="text-xs mt-2" style={{ color: "#D4610D" }}>{avisoFoto}</p>}
    </Modal>
  );
}

export default function LeitorCodigoBarras({ onLido, quadrado = false, lido }: {
  /** Recebe só os números lidos. */
  onLido: (codigo: string) => void;
  /** Quadrado grande (cadastro de produto). Sem isso: botão pequeno só com ícone (ao lado da busca). */
  quadrado?: boolean;
  /** Código já lido, mostrado dentro do quadrado. */
  lido?: string;
}) {
  const [aberto, setAberto] = useState(false);

  const aoLer = (codigo: string) => {
    setAberto(false);
    onLido(codigo.replace(/\D/g, "") || codigo);
  };

  return (
    <>
      {quadrado ? (
        <button
          type="button"
          onClick={() => setAberto(true)}
          className="w-full aspect-square sm:aspect-auto sm:h-36 flex flex-col items-center justify-center gap-1.5 p-3 rounded-lg border-2 border-dashed bg-white hover:bg-cream transition-fast text-center"
          style={{ borderColor: lido ? "#1A7C4E" : "#D8D4CC" }}
        >
          <ScanBarcode size={30} style={{ color: lido ? "#1A7C4E" : "#C9281F" }} />
          <span className="text-charcoal text-sm font-semibold leading-tight">Escanear código do produto</span>
          <span className="text-xs leading-tight" style={{ color: lido ? "#1A7C4E" : "#777A78", fontFamily: lido ? "var(--font-mono)" : undefined }}>
            {lido ? `EAN ${lido}` : "Código de barras (EAN)"}
          </span>
        </button>
      ) : (
        <button
          type="button"
          onClick={() => setAberto(true)}
          className="inline-flex items-center justify-center px-3 py-2 rounded-md border bg-white text-charcoal hover:bg-cream transition-fast flex-shrink-0"
          style={{ borderColor: "#D8D4CC" }}
          title="Escanear código de barras"
          aria-label="Escanear código de barras"
        >
          <ScanBarcode size={18} />
        </button>
      )}
      {aberto && <JanelaLeitura onLido={aoLer} onFechar={() => setAberto(false)} />}
    </>
  );
}
