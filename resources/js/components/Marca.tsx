import { usePage } from '@inertiajs/react';

export type UsuarioLogado = {
  id: number;
  name: string;
  email: string;
  perfil: string;
};

/** Dados da oficina, salvos pelo administrador em Configurações → Empresa. */
export type DadosOficina = {
  nome: string;
  logo_url: string | null;
  whatsapp: string | null;
  endereco: string | null;
};

export function useUsuario(): UsuarioLogado | null {
  return (usePage().props as unknown as { auth: { user: UsuarioLogado | null } }).auth.user;
}

export function useOficina(): DadosOficina | null {
  return (usePage().props as unknown as { oficina: DadosOficina | null }).oficina ?? null;
}

/** Logo da oficina; sem logo, mostra a inicial do nome no quadrado vermelho. */
export function FotoEmpresa({ tamanho = 32, redonda = false }: { tamanho?: number; redonda?: boolean }) {
  const oficina = useOficina();
  const forma = redonda ? 'rounded-full' : 'rounded';

  if (oficina?.logo_url) {
    return (
      <img
        src={oficina.logo_url}
        alt={oficina.nome}
        className={`${forma} flex-shrink-0 bg-white object-cover`}
        style={{ width: tamanho, height: tamanho }}
      />
    );
  }

  const inicial = (oficina?.nome || 'G').trim().charAt(0).toUpperCase();

  return (
    <div
      className={`${forma} flex flex-shrink-0 items-center justify-center font-bold text-white`}
      style={{ width: tamanho, height: tamanho, background: '#C9281F', fontSize: tamanho * 0.45 }}
    >
      {inicial}
    </div>
  );
}
