import { useState, type FormEvent } from "react";
import { Link, useForm } from "@inertiajs/react";
import { Plus, ShoppingCart, TrendingUp } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Card, StatCard, Table, Th, Td, StatusBadge, Modal } from "@/components/ui";

type Venda = {
  id: number; data: string; itens: number; resumo: string; forma: string; total: number;
  status: string; motivo_cancelamento: string | null; usuario: string;
};
type Props = { vendas: Venda[]; resumo: { quantidade: number; faturamento: number } };

const brl = (v: number) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);

function CancelarModal({ venda, onClose }: { venda: Venda; onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm({ motivo: "" });

  const enviar = (e: FormEvent) => {
    e.preventDefault();
    post(`/vendas/${venda.id}/cancelar`, { preserveScroll: true, onSuccess: () => onClose() });
  };

  return (
    <form onSubmit={enviar} className="space-y-4">
      <p className="text-sm text-charcoal">
        A venda <b>#{venda.id}</b> ({brl(venda.total)}) será marcada como cancelada e os itens voltarão ao estoque. O registro original continua no histórico.
      </p>
      <div>
        <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>Motivo *</label>
        <textarea
          value={data.motivo}
          onChange={(e) => setData("motivo", e.target.value)}
          rows={2}
          placeholder="Ex.: cliente desistiu, produto errado..."
          className="w-full px-3 py-2 rounded-md border text-charcoal text-sm focus:outline-none focus:ring-1 focus:ring-red resize-none"
          style={{ borderColor: "#D8D4CC" }}
        />
        {errors.motivo && <p className="text-red text-xs mt-1">{errors.motivo}</p>}
      </div>
      <div className="flex justify-end gap-3">
        <Button variant="secondary" onClick={onClose}>Voltar</Button>
        <Button variant="danger" type="submit" disabled={processing}>{processing ? "Cancelando..." : "Cancelar venda"}</Button>
      </div>
    </form>
  );
}

export default function VendasIndex({ vendas, resumo }: Props) {
  const [cancelando, setCancelando] = useState<Venda | null>(null);

  return (
    <AppLayout title="Vendas">
      <PageHeader title="Vendas" subtitle="Vendas de balcão registradas no sistema.">
        <Link href="/vendas/nova"><Button variant="primary"><Plus size={14} /> Nova venda</Button></Link>
      </PageHeader>

      <div className="grid grid-cols-2 gap-4 mb-6 max-w-xl">
        <StatCard label="Vendas hoje" value={`${resumo.quantidade}`} sub="confirmadas" icon={ShoppingCart} />
        <StatCard label="Faturamento hoje" value={brl(resumo.faturamento)} sub="sem as canceladas" icon={TrendingUp} variant="success" />
      </div>

      <Card>
        <Table>
          <thead>
            <tr>
              <Th>#</Th><Th>Data</Th><Th>Produtos</Th><Th>Pagamento</Th>
              <Th className="text-right">Total</Th><Th>Status</Th><Th>Usuário</Th><Th></Th>
            </tr>
          </thead>
          <tbody>
            {vendas.map((v) => (
              <tr key={v.id} className="hover:bg-cream/40 transition-fast">
                <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>#{v.id}</span></Td>
                <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12 }}>{v.data}</span></Td>
                <Td>
                  <div className="text-charcoal text-sm">{v.itens} {v.itens === 1 ? "item" : "itens"}</div>
                  <div className="text-gray-text text-xs truncate" style={{ maxWidth: 320 }}>{v.resumo}</div>
                </Td>
                <Td><span className="text-gray-text text-sm">{v.forma}</span></Td>
                <Td className="text-right">
                  <span style={{ fontFamily: "var(--font-mono)", fontWeight: 500, textDecoration: v.status === "Cancelada" ? "line-through" : undefined }}>
                    {brl(v.total)}
                  </span>
                </Td>
                <Td>
                  <StatusBadge status={v.status} />
                  {v.motivo_cancelamento && <div className="text-gray-text text-xs mt-1">{v.motivo_cancelamento}</div>}
                </Td>
                <Td><span className="text-gray-text text-sm">{v.usuario}</span></Td>
                <Td>
                  {v.status === "Concluída" && (
                    <button onClick={() => setCancelando(v)} className="text-xs font-medium text-red hover:underline">Cancelar</button>
                  )}
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {vendas.length === 0 && <div className="py-12 text-center text-gray-text text-sm">Nenhuma venda registrada.</div>}
      </Card>

      {cancelando && (
        <Modal title={`Cancelar venda #${cancelando.id}`} onClose={() => setCancelando(null)}>
          <CancelarModal venda={cancelando} onClose={() => setCancelando(null)} />
        </Modal>
      )}
    </AppLayout>
  );
}