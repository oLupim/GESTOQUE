import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useEffect, useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { useOficina, useUsuario } from '@/components/Marca';
import { reduzirImagem } from '@/lib/imagem';

type Props = {
  perfil: { name: string; email: string };
  fiscal: {
    razao_social: string | null;
    cnpj: string | null;
    inscricao_estadual: string | null;
    crt: number;
    ambiente: number;
    tem_token: boolean;
  };
};

const campo =
  'w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-[#C9281F] focus:outline-none';
const botao =
  'rounded-md bg-[#C9281F] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50';

function Campo({ label, erro, children }: { label: string; erro?: string; children: ReactNode }) {
  return (
    <label className="block space-y-1">
      <span className="text-sm font-medium text-neutral-700">{label}</span>
      {children}
      {erro && <span className="block text-xs text-[#C9281F]">{erro}</span>}
    </label>
  );
}

function Cartao({ titulo, descricao, children }: { titulo: string; descricao: string; children: ReactNode }) {
  return (
    <section className="rounded-lg border border-neutral-200 bg-white p-4 sm:p-6">
      <h2 className="text-lg font-semibold">{titulo}</h2>
      <p className="mb-4 text-sm text-neutral-500">{descricao}</p>
      {children}
    </section>
  );
}

export default function Configuracoes({ perfil, fiscal }: Props) {
  const usuario = useUsuario();
  const oficina = useOficina();
  const ehAdmin = usuario?.perfil === 'administrador';
  const e = useForm<{ empresa: string; whatsapp: string; endereco: string; foto: File | null; remover_foto: boolean }>({
    empresa: oficina?.nome ?? '',
    whatsapp: oficina?.whatsapp ?? '',
    endereco: oficina?.endereco ?? '',
    foto: null,
    remover_foto: false,
  });
  // Prévia local do arquivo escolhido, antes de enviar.
  const [previaLocal, setPreviaLocal] = useState<string | null>(null);
  // Muda a key do input de arquivo para limpá-lo depois de salvar/remover.
  const [inputKey, setInputKey] = useState(0);

  useEffect(() => {
    if (!e.data.foto) {
      setPreviaLocal(null);
      return;
    }
    const url = URL.createObjectURL(e.data.foto);
    setPreviaLocal(url);
    return () => URL.revokeObjectURL(url);
  }, [e.data.foto]);

  const previa = previaLocal ?? (e.data.remover_foto ? null : oficina?.logo_url ?? null);
  const inicial = (e.data.empresa || 'G').trim().charAt(0).toUpperCase();

  // Foto do celular é grande: reduz no próprio navegador antes de enviar.
  const [preparando, setPreparando] = useState(false);
  const escolherFoto = async (arquivo: File | null) => {
    if (!arquivo) return;
    setPreparando(true);
    try {
      const reduzida = await reduzirImagem(arquivo, { maxLado: 512 });
      e.setData((d) => ({ ...d, foto: reduzida, remover_foto: false }));
    } finally {
      setPreparando(false);
    }
  };

  const removerFoto = () => {
    e.setData((d) => ({ ...d, foto: null, remover_foto: true }));
    setInputKey((k) => k + 1);
  };
  const salvarEmpresa = (ev: FormEvent) => {
    ev.preventDefault();
    e.post('/configuracoes/empresa', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        e.setData((d) => ({ ...d, foto: null, remover_foto: false }));
        setInputKey((k) => k + 1);
      },
    });
  };

  const p = useForm({ name: perfil.name, email: perfil.email });
  const s = useForm({ senha_atual: '', password: '', password_confirmation: '' });
  const f = useForm({
    razao_social: fiscal.razao_social ?? '',
    cnpj: fiscal.cnpj ?? '',
    inscricao_estadual: fiscal.inscricao_estadual ?? '',
    crt: String(fiscal.crt ?? 1),
    ambiente: String(fiscal.ambiente ?? 2),
    api_token: '',
  });

  const salvarPerfil = (e: FormEvent) => {
    e.preventDefault();
    p.put('/configuracoes/perfil', { preserveScroll: true });
  };
  const salvarSenha = (e: FormEvent) => {
    e.preventDefault();
    s.put('/configuracoes/senha', { preserveScroll: true, onSuccess: () => s.reset() });
  };
  const salvarFiscal = (e: FormEvent) => {
    e.preventDefault();
    f.put('/configuracoes/fiscal', { preserveScroll: true, onSuccess: () => f.reset('api_token') });
  };

  return (
    <AppLayout title="Configurações">      
    <Head title="Configurações" />
      <div className="mx-auto max-w-3xl space-y-6">
        <h1 className="text-2xl font-bold">Configurações</h1>

        {/* Só o administrador altera os dados da oficina. */}
        {ehAdmin && (
        <Cartao titulo="Empresa" descricao="Nome e logo aparecem no menu, no topo e no catálogo público. WhatsApp e endereço aparecem no catálogo.">
          <form onSubmit={salvarEmpresa} className="grid gap-4 sm:grid-cols-2">
            <div className="flex items-center gap-4 sm:col-span-2">
              {previa ? (
                <img src={previa} alt="Logo da empresa" className="h-20 w-20 rounded border border-neutral-200 bg-white object-cover" />
              ) : (
                <div className="flex h-20 w-20 items-center justify-center rounded text-3xl font-bold text-white" style={{ background: '#C9281F' }}>
                  {inicial}
                </div>
              )}
              {previa && (
                <button type="button" onClick={removerFoto} className="text-sm font-medium text-[#C9281F] hover:underline">
                  Remover foto
                </button>
              )}
            </div>
            <Campo label="Nome da empresa" erro={e.errors.empresa}>
              <input className={campo} value={e.data.empresa} onChange={(ev) => e.setData('empresa', ev.target.value)} />
            </Campo>
            <Campo label="Logo (foto da galeria ou da câmera)" erro={e.errors.foto}>
              {/* image/* faz o celular oferecer "Câmera" e "Galeria". */}
              <input
                key={inputKey}
                type="file"
                accept="image/*"
                className={campo}
                disabled={preparando}
                onChange={(ev) => escolherFoto(ev.target.files?.[0] ?? null)}
              />
              {preparando && <span className="block text-xs text-neutral-500">Preparando imagem…</span>}
            </Campo>
            <Campo label="WhatsApp (com 55 e DDD)" erro={e.errors.whatsapp}>
              <input
                className={campo}
                value={e.data.whatsapp}
                onChange={(ev) => e.setData('whatsapp', ev.target.value)}
                placeholder="55 54 99999-9999 (deixe vazio para esconder os botões)"
              />
            </Campo>
            <Campo label="Endereço" erro={e.errors.endereco}>
              <input
                className={campo}
                value={e.data.endereco}
                onChange={(ev) => e.setData('endereco', ev.target.value)}
                placeholder="Rua, número, cidade/UF"
              />
            </Campo>
            <div className="sm:col-span-2">
              <button className={botao} disabled={e.processing || preparando}>Salvar empresa</button>
            </div>
          </form>
        </Cartao>
        )}

        <Cartao titulo="Minha conta" descricao="Nome e e-mail usados no login.">
          <form onSubmit={salvarPerfil} className="grid gap-4 sm:grid-cols-2">
            <Campo label="Nome" erro={p.errors.name}>
              <input className={campo} value={p.data.name} onChange={(e) => p.setData('name', e.target.value)} />
            </Campo>
            <Campo label="E-mail" erro={p.errors.email}>
              <input type="email" className={campo} value={p.data.email} onChange={(e) => p.setData('email', e.target.value)} />
            </Campo>
            <div className="sm:col-span-2">
              <button className={botao} disabled={p.processing}>Salvar dados</button>
            </div>
          </form>
        </Cartao>

        <Cartao titulo="Senha" descricao="Para trocar, confirme a senha atual.">
          <form onSubmit={salvarSenha} className="grid gap-4 sm:grid-cols-3">
            <Campo label="Senha atual" erro={s.errors.senha_atual}>
              <input type="password" className={campo} value={s.data.senha_atual} onChange={(e) => s.setData('senha_atual', e.target.value)} />
            </Campo>
            <Campo label="Nova senha" erro={s.errors.password}>
              <input type="password" className={campo} value={s.data.password} onChange={(e) => s.setData('password', e.target.value)} />
            </Campo>
            <Campo label="Repetir nova senha">
              <input type="password" className={campo} value={s.data.password_confirmation} onChange={(e) => s.setData('password_confirmation', e.target.value)} />
            </Campo>
            <div className="sm:col-span-3">
              <button className={botao} disabled={s.processing}>Alterar senha</button>
            </div>
          </form>
        </Cartao>

        <Cartao titulo="Fiscal (Brasil NFe)" descricao="Dados do emitente e token da API usados na emissão de notas.">
          <form onSubmit={salvarFiscal} className="grid gap-4 sm:grid-cols-2">
            <Campo label="Razão social" erro={f.errors.razao_social}>
              <input className={campo} value={f.data.razao_social} onChange={(e) => f.setData('razao_social', e.target.value)} />
            </Campo>
            <Campo label="CNPJ" erro={f.errors.cnpj}>
              <input className={campo} value={f.data.cnpj} onChange={(e) => f.setData('cnpj', e.target.value)} placeholder="Só números ou com pontuação" />
            </Campo>
            <Campo label="Inscrição estadual" erro={f.errors.inscricao_estadual}>
              <input className={campo} value={f.data.inscricao_estadual} onChange={(e) => f.setData('inscricao_estadual', e.target.value)} />
            </Campo>
            <Campo label="Regime tributário (CRT)" erro={f.errors.crt}>
              <select className={campo} value={f.data.crt} onChange={(e) => f.setData('crt', e.target.value)}>
                <option value="1">1 · Simples Nacional</option>
                <option value="2">2 · Simples Nacional (excesso de sublimite)</option>
                <option value="3">3 · Regime Normal</option>
                <option value="4">4 · MEI</option>
              </select>
            </Campo>
            <Campo label="Ambiente" erro={f.errors.ambiente}>
              <select className={campo} value={f.data.ambiente} onChange={(e) => f.setData('ambiente', e.target.value)}>
                <option value="2">Homologação (teste, sem valor fiscal)</option>
                <option value="1">Produção (nota real)</option>
              </select>
            </Campo>
            <Campo label="Token da API" erro={f.errors.api_token}>
              <input
                type="password"
                className={campo}
                value={f.data.api_token}
                onChange={(e) => f.setData('api_token', e.target.value)}
                placeholder={fiscal.tem_token ? '•••••• salvo — deixe vazio para manter' : 'Cole o token da Brasil NFe'}
              />
            </Campo>
            <div className="sm:col-span-2">
              <button className={botao} disabled={f.processing}>Salvar configuração fiscal</button>
            </div>
          </form>
        </Cartao>
      </div>
    </AppLayout>
  );
}