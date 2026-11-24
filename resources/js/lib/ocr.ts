import { reduzirImagem } from "@/lib/imagem";

/** Uma linha de texto lida na foto: o texto, a altura da letra (em pixels) e a confiança (0–100). */
export type LinhaLida = { texto: string; altura: number; confianca: number };

/**
 * Lê o texto de uma foto (OCR) no próprio celular, com o Tesseract (código aberto, grátis).
 * A foto não sai do aparelho. Na primeira vez o navegador baixa o programa e o "dicionário"
 * de português (~4 MB); depois fica guardado. O leitor é criado uma vez e reaproveitado.
 */
type Leitor = { recognize: (imagem: File, opcoes?: object, saida?: object) => Promise<{ data: { blocks: Bloco[] | null } }> };
type Bloco = { paragraphs: { lines: { text: string; confidence: number; bbox: { y0: number; y1: number } }[] }[] };

let leitor: Promise<Leitor> | null = null;

function criarLeitor(): Promise<Leitor> {
  leitor ??= import("tesseract.js").then(({ createWorker }) => createWorker("por") as unknown as Promise<Leitor>);
  // Se falhar (ex.: sem internet na primeira vez), deixa tentar de novo na próxima foto.
  leitor.catch(() => { leitor = null; });
  return leitor;
}

export async function lerTextoDaFoto(arquivo: File): Promise<LinhaLida[]> {
  // Tamanho médio: grande o bastante para letras pequenas, pequeno o bastante para ser rápido no celular.
  const foto = await reduzirImagem(arquivo, { maxLado: 1600, qualidade: 0.95 });
  const tesseract = await criarLeitor();
  const { data } = await tesseract.recognize(foto, { rotateAuto: true }, { blocks: true });

  return (data.blocks ?? [])
    .flatMap((b) => b.paragraphs.flatMap((p) => p.lines))
    .map((l) => ({ texto: l.text.trim(), altura: Math.round(l.bbox.y1 - l.bbox.y0), confianca: Math.round(l.confidence) }))
    .filter((l) => l.texto.length > 0)
    .slice(0, 80);
}
