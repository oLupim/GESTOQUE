/**
 * Reduz uma imagem no próprio navegador antes do envio.
 * Uma foto de celular (3–8 MB, 4000+ px) vira um arquivo de poucos KB,
 * sem a pessoa precisar editar nada. Funciona no celular e no computador.
 */

type Opcoes = {
  /** Maior lado da imagem final, em pixels. */
  maxLado?: number;
  /** Qualidade de 0 a 1 (WebP/JPEG). */
  qualidade?: number;
};

/** Abre a imagem respeitando a orientação da câmera (foto não fica deitada). */
async function carregar(arquivo: File): Promise<ImageBitmap | HTMLImageElement> {
  if ('createImageBitmap' in window) {
    try {
      return await createImageBitmap(arquivo, { imageOrientation: 'from-image' });
    } catch {
      // Alguns navegadores não aceitam a opção: tenta pelo <img> abaixo.
    }
  }

  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(arquivo);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Não foi possível ler a imagem.'));
    };
    img.src = url;
  });
}

function paraBlob(canvas: HTMLCanvasElement, tipo: string, qualidade: number): Promise<Blob | null> {
  return new Promise((resolve) => canvas.toBlob(resolve, tipo, qualidade));
}

/**
 * Devolve um novo arquivo menor. Se o navegador não conseguir ler o formato
 * (ex.: HEIC num Android), devolve o original e o servidor avisa se não servir.
 */
export async function reduzirImagem(arquivo: File, { maxLado = 1024, qualidade = 0.85 }: Opcoes = {}): Promise<File> {
  if (!arquivo.type.startsWith('image/')) return arquivo;

  let imagem: ImageBitmap | HTMLImageElement;
  try {
    imagem = await carregar(arquivo);
  } catch {
    return arquivo;
  }

  const largura = 'naturalWidth' in imagem ? imagem.naturalWidth : imagem.width;
  const altura = 'naturalHeight' in imagem ? imagem.naturalHeight : imagem.height;
  const escala = Math.min(1, maxLado / Math.max(largura, altura));

  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(largura * escala));
  canvas.height = Math.max(1, Math.round(altura * escala));
  const ctx = canvas.getContext('2d');
  if (!ctx) return arquivo;
  ctx.drawImage(imagem, 0, 0, canvas.width, canvas.height);
  if ('close' in imagem) imagem.close();

  // WebP mantém transparência e é leve. Se o navegador não gerar WebP,
  // usa PNG para imagens PNG (logo com fundo transparente) e JPEG para fotos.
  let blob = await paraBlob(canvas, 'image/webp', qualidade);
  if (!blob || blob.type !== 'image/webp') {
    const tipo = arquivo.type === 'image/png' ? 'image/png' : 'image/jpeg';
    blob = await paraBlob(canvas, tipo, qualidade);
  }
  if (!blob) return arquivo;

  // Se por acaso o resultado ficou maior (imagem já pequena), mantém o original.
  if (blob.size >= arquivo.size && ['image/jpeg', 'image/png', 'image/webp'].includes(arquivo.type)) {
    return arquivo;
  }

  const extensao = blob.type.split('/')[1].replace('jpeg', 'jpg');
  const nome = arquivo.name.replace(/\.[^.]+$/, '') || 'imagem';

  return new File([blob], `${nome}.${extensao}`, { type: blob.type, lastModified: Date.now() });
}
