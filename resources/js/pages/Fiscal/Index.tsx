import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

type Documento = {
  id: number;
  venda_id: number;
  total: number;
  modelo: number;
  ambiente: number;
  status: string;
  numero: string | null;
  serie: string | null;
  chave: string | null;
  mensagem: string | null;
  tentativas: number;
  data: string;
  tem_xml: boolean;
  tem_pdf: boolean;
  pode_enviar: boolean;
};

type Props = {
  documentos: { data: Documento[]; prev_page_url: string | null; next_page_url: string | null; total: number };
  contagem: Record<string, number>;
  filtro: string | null;
  config: { pronta: boolean; ambiente: number };
};

const STATUS: Record<string, { rotulo: string; cor: string }> = {
  autorizada: { rotulo: 'Autorizada', cor: 'bg-green-100 text-green-800' },
  rejeitada: { rotulo: 'Rejeitada', cor: 'bg-red-100 text-red-800' },
  erro: { rotulo: 'Erro', cor: 'bg-orange-100 text-orange-800' },
  processando: { rotulo: 'Processando', cor: 'bg-blue-100 text-blue-800' },
  pendente: { rotulo: 'Pendente', cor: 'bg-neutral-100 text-neutral-700' },
  cancelada: { rotulo: 'Cancelada', cor: 'bg-neutral-200 text-neutral-600' },
};

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Fiscal({ documentos, contagem, filtro, config }: Props) {
  return (
    <AppLayout>
      <Head title="Fiscal" />
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-2xl font-bold">Fiscal</h1>
          <span className={`rounded-full px-3 py-1 text-xs font-semibold ${config.ambiente === 1 ? 'bg-[#C9281F] text-white' : 'bg-amber-100 text-amber-800'}`}>
            {config.ambiente === 1 ? 'PRODUÇÃO — notas com valor fiscal' : 'HOMOLOGAÇÃO — notas de teste'}
          </span>
        </div>

        {!config.pronta && (
          <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            Configuração fiscal incompleta. Preencha CNPJ, razão social e token em{' '}
            <Link href="/configuracoes" className="font-semibold underline">Configurações</Link> antes de emitir.
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          <Link href="/fiscal" className={`rounded-full border px-3 py-1 text-sm ${!filtro ? 'border-[#C9281F] font-semibold' : 'border-neutral-300'}`}>
            Todas
          </Link>
          {Object.entries(STATUS).map(([chave, s]) => (
            <Link
              key={chave}
              href={`/fiscal?status=${chave}`}
              className={`rounded-full border px-3 py-1 text-sm ${filtro === chave ? 'border-[#C9281F] font-semibold' : 'border-neutral-300'}`}
            >
              {s.rotulo} ({contagem[chave] ?? 0})
            </Link>
          ))}
        </div>

        <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
              <tr>
                <th className="px-4 py-3">Data</th>
                <th className="px-4 py-3">Venda</th>
                <th className="px-4 py-3">Nota</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3 text-right">Total</th>
                <th className="px-4 py-3 text-right">Ações</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {documentos.data.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                    Nenhuma nota ainda. Emita pela tela de venda.
                  </td>
                </tr>
              )}
              {documentos.data.map((d) => {
                const s = STATUS[d.status] ?? { rotulo: d.status, cor: 'bg-neutral-100' };
                return (
                  <tr key={d.id} className="align-top">
                    <td className="px-4 py-3 whitespace-nowrap">{d.data}</td>
                    <td className="px-4 py-3">#{d.venda_id}</td>
                    <td className="px-4 py-3">
                      <div>{d.modelo === 65 ? 'NFC-e' : 'NF-e'} {d.numero ? `nº ${d.numero}/${d.serie}` : '—'}</div>
                      {d.chave && <div className="font-mono text-xs text-neutral-500 break-all">{d.chave}</div>}
                      {d.mensagem && d.status !== 'autorizada' && (
                        <div className="mt-1 text-xs text-[#C9281F]">{d.mensagem}</div>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${s.cor}`}>{s.rotulo}</span>
                      {d.tentativas > 1 && <div className="mt-1 text-xs text-neutral-500">{d.tentativas} tentativas</div>}
                    </td>
                    <td className="px-4 py-3 text-right">{brl(d.total)}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-2">
                        {d.tem_pdf && <a href={`/fiscal/${d.id}/pdf`} className="rounded border border-neutral-300 px-2 py-1 text-xs">DANFE</a>}
                        {d.tem_xml && <a href={`/fiscal/${d.id}/xml`} className="rounded border border-neutral-300 px-2 py-1 text-xs">XML</a>}
                        {d.pode_enviar && (
                          <Link
                            href={`/vendas/${d.venda_id}/nfce`}
                            method="post"
                            as="button"
                            preserveScroll
                            className="rounded bg-[#C9281F] px-2 py-1 text-xs font-semibold text-white"
                          >
                            Reenviar
                          </Link>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="flex justify-between text-sm">
          {documentos.prev_page_url ? <Link href={documentos.prev_page_url}>← Anteriores</Link> : <span />}
          {documentos.next_page_url && <Link href={documentos.next_page_url}>Próximas →</Link>}
        </div>
      </div>
    </AppLayout>
  );
}