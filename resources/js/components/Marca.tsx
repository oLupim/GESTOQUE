import { usePage } from '@inertiajs/react';

export type UsuarioLogado = {
  id: number;
  name: string;
  email: string;
  perfil: string;
  empresa: string | null;
  foto_url: string | null;
};

export function useUsuario(): UsuarioLogado | null {
  return (usePage().props as unknown as { auth: { user: UsuarioLogado | null } }).auth.user;
}

/** Logo da empresa; sem foto, mostra a inicial do nome da empresa. */
export function FotoEmpresa({ tamanho = 32, redonda = false }: { tamanho?: number; redonda?: boolean }) {
  const usuario = useUsuario();
  const forma = redonda ? 'rounded-full' : 'rounded';

  if (usuario?.foto_url) {
    return (
      <img
        src={usuario.foto_url}
        alt={usuario.empresa ?? 'Logo da empresa'}
        className={`${forma} flex-shrink-0 bg-white object-cover`}
        style={{ width: tamanho, height: tamanho }}
      />
    );
  }

  const inicial = (usuario?.empresa || usuario?.name || 'G').trim().charAt(0).toUpperCase();

  return (
    <div
      className={`${forma} flex flex-shrink-0 items-center justify-center font-bold text-white`}
      style={{ width: tamanho, height: tamanho, background: '#C9281F', fontSize: tamanho * 0.45 }}
    >
      {inicial}
    </div>
  );
}