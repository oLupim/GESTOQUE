import { useState, type ReactNode } from "react";
import { Input } from "@/components/ui";

/**
 * Campo de texto com lista de sugestões embaixo (autocompletar).
 * As sugestões aparecem enquanto o campo está em foco e somem ao escolher uma ou sair do campo.
 * Funciona no toque: a lista fecha com um pequeno atraso para o toque no item ser registrado antes.
 */
export default function Autocompletar<T>({
  value,
  onChange,
  sugestoes,
  chave,
  renderItem,
  onEscolher,
  placeholder,
  className = "",
  prefix,
  rodape,
}: {
  value: string;
  onChange: (v: string) => void;
  /** Sugestões já filtradas pelo texto digitado (o componente mostra no máximo 6). */
  sugestoes: T[];
  chave: (item: T) => string | number;
  renderItem: (item: T) => ReactNode;
  onEscolher: (item: T) => void;
  placeholder?: string;
  className?: string;
  prefix?: ReactNode;
  /** Texto pequeno no fim da lista (ex.: explicação do que acontece ao escolher). */
  rodape?: string;
}) {
  const [aberto, setAberto] = useState(false);
  const lista = sugestoes.slice(0, 6);

  return (
    <div
      className={`relative ${className}`}
      onFocus={() => setAberto(true)}
      onBlur={() => setTimeout(() => setAberto(false), 150)}
    >
      <Input value={value} onChange={(v) => { onChange(v); setAberto(true); }} placeholder={placeholder} prefix={prefix} />
      {aberto && lista.length > 0 && (
        <div className="absolute top-full mt-1 left-0 right-0 bg-white rounded-lg shadow-xl z-20 py-1 max-h-72 overflow-y-auto" style={{ border: "1px solid #E5E2DA" }}>
          {lista.map((item) => (
            <button
              key={chave(item)}
              type="button"
              onClick={() => { onEscolher(item); setAberto(false); }}
              className="w-full px-3 sm:px-4 py-2.5 hover:bg-cream text-left"
            >
              {renderItem(item)}
            </button>
          ))}
          {rodape && <div className="px-3 sm:px-4 pt-1 pb-1.5 text-gray-text" style={{ fontSize: 11 }}>{rodape}</div>}
        </div>
      )}
    </div>
  );
}

/** Compara textos sem diferenciar maiúsculas e acentos ("oleo" acha "Óleo"). */
export function contem(texto: string | null | undefined, busca: string): boolean {
  const limpar = (s: string) => s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
  return limpar(texto ?? "").includes(limpar(busca.trim()));
}
