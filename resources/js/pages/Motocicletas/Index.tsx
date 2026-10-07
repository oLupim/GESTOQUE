import { useState } from "react";
import { Plus, Search, Bike } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Card, Table, Th, Td, Modal } from "@/components/ui";
import { MotoForm, formatarPlaca, formatarTelefone } from "@/components/Cadastros";

type Moto = {
  id: number; placa: string; marca: string; modelo: string; ano: number | null; cor: string | null;
  km_atual: number; cliente: string; cliente_id: number; telefone: string | null;
};
type Props = { motos: Moto[]; clientes: { id: number; nome: string }[] };

export default function MotocicletasIndex({ motos, clientes }: Props) {
  const [busca, setBusca] = useState("");
  const [nova, setNova] = useState(false);

  const q = busca.toLowerCase().replace(/[^a-z0-9 ]/g, "");
  const filtradas = motos.filter((m) =>
    !q || `${m.marca} ${m.modelo} ${m.placa} ${m.cliente}`.toLowerCase().includes(q),
  );

  return (
    <AppLayout title="Motocicletas">
      <PageHeader title="Motocicletas" subtitle="Motos atendidas pela oficina e seus proprietários.">
        <Button variant="primary" onClick={() => setNova(true)} disabled={clientes.length === 0}><Plus size={14} /> Nova motocicleta</Button>
      </PageHeader>

      {clientes.length === 0 && (
        <div className="mb-5 p-4 rounded-lg text-sm text-charcoal" style={{ background: "rgba(212,97,13,0.06)", border: "1px solid rgba(212,97,13,0.2)" }}>
          Cadastre um cliente antes: toda moto precisa de um proprietário.
        </div>
      )}

      <div className="flex items-center gap-3 mb-5">
        <Input className="w-72" placeholder="Buscar por modelo, placa ou proprietário..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
        <span className="text-gray-text text-sm ml-auto">{filtradas.length} motos</span>
      </div>

      <Card>
        <Table>
          <thead>
            <tr><Th>Motocicleta</Th><Th>Placa</Th><Th>Ano</Th><Th>Proprietário</Th><Th className="text-right">Quilometragem</Th></tr>
          </thead>
          <tbody>
            {filtradas.map((m) => (
              <tr key={m.id} className="hover:bg-cream/40 transition-fast">
                <Td>
                  <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-lg flex items-center justify-center" style={{ background: "#F0EDE5" }}><Bike size={16} style={{ color: "#777A78" }} /></div>
                    <div>
                      <div className="text-charcoal font-medium">{m.marca} {m.modelo}</div>
                      <div className="text-gray-text text-xs">{m.cor ?? "—"}</div>
                    </div>
                  </div>
                </Td>
                <Td><span className="px-2 py-0.5 rounded text-xs" style={{ background: "#F0EDE5", color: "#777A78", fontFamily: "var(--font-mono)" }}>{formatarPlaca(m.placa)}</span></Td>
                <Td><span className="text-gray-text text-sm">{m.ano ?? "—"}</span></Td>
                <Td>
                  <div className="text-charcoal text-sm">{m.cliente}</div>
                  <div className="text-gray-text text-xs">{formatarTelefone(m.telefone)}</div>
                </Td>
                <Td className="text-right"><span style={{ fontFamily: "var(--font-mono)", fontSize: 13 }}>{m.km_atual.toLocaleString("pt-BR")} km</span></Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {filtradas.length === 0 && <div className="py-12 text-center text-gray-text text-sm">Nenhuma moto encontrada.</div>}
      </Card>

      {nova && (
        <Modal title="Nova motocicleta" onClose={() => setNova(false)} width="max-w-2xl">
          <MotoForm clientes={clientes} onClose={() => setNova(false)} />
        </Modal>
      )}
    </AppLayout>
  );
}