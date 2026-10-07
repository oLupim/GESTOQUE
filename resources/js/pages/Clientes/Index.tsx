import { useState } from "react";
import { Plus, Search, Phone, ChevronRight, Bike, X } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Card, Table, Th, Td, Modal } from "@/components/ui";
import { ClienteForm, MotoForm, formatarDoc, formatarPlaca, formatarTelefone, type Cliente } from "@/components/Cadastros";

function Detalhe({ cliente, onClose, onEditar, onNovaMoto }: { cliente: Cliente; onClose: () => void; onEditar: () => void; onNovaMoto: () => void }) {
  const [aba, setAba] = useState<"dados" | "motos">("dados");

  return (
    <div className="fixed inset-0 z-40 flex justify-end">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className="relative w-full max-w-xl bg-white h-full overflow-y-auto shadow-2xl">
        <div className="sticky top-0 bg-white px-6 py-5 flex items-start justify-between" style={{ borderBottom: "1px solid #F0EDE5" }}>
          <div className="flex items-center gap-3">
            <div className="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold text-lg bg-red" style={{ fontFamily: "var(--font-condensed)" }}>
              {cliente.nome.split(" ").map((n) => n[0]).join("").slice(0, 2).toUpperCase()}
            </div>
            <div>
              <div className="text-charcoal font-bold" style={{ fontFamily: "var(--font-condensed)", fontSize: 18 }}>{cliente.nome}</div>
              <div className="text-gray-text text-xs">{formatarDoc(cliente.cpf_cnpj)}</div>
            </div>
          </div>
          <button onClick={onClose} className="text-gray-text hover:text-charcoal p-1"><X size={18} /></button>
        </div>

        <div className="flex px-6" style={{ borderBottom: "1px solid #E5E2DA" }}>
          {(["dados", "motos"] as const).map((a) => (
            <button
              key={a}
              onClick={() => setAba(a)}
              className={`px-4 py-2.5 text-sm font-medium ${aba === a ? "text-red border-b-2 border-red" : "text-gray-text hover:text-charcoal"}`}
              style={{ marginBottom: -1 }}
            >
              {a === "dados" ? "Dados" : `Motos (${cliente.motos.length})`}
            </button>
          ))}
        </div>

        <div className="p-6">
          {aba === "dados" && (
            <div className="space-y-4">
              {[
                ["Telefone", formatarTelefone(cliente.telefone)],
                ["CPF / CNPJ", formatarDoc(cliente.cpf_cnpj)],
                ["E-mail", cliente.email ?? "—"],
                ["Endereço", cliente.endereco ?? "—"],
                ["Observações", cliente.observacoes ?? "—"],
              ].map(([rotulo, valor]) => (
                <div key={rotulo}>
                  <div className="text-gray-text uppercase mb-1" style={{ fontSize: 10.5, letterSpacing: "0.06em" }}>{rotulo}</div>
                  <div className="text-charcoal text-sm">{valor}</div>
                </div>
              ))}
              <Button variant="secondary" size="sm" onClick={onEditar}>Editar dados</Button>
            </div>
          )}

          {aba === "motos" && (
            <div className="space-y-3">
              {cliente.motos.map((m) => (
                <div key={m.id} className="p-4 rounded-lg flex items-start gap-3" style={{ border: "1px solid #E5E2DA" }}>
                  <div className="w-9 h-9 rounded flex items-center justify-center" style={{ background: "#F0EDE5" }}><Bike size={16} style={{ color: "#777A78" }} /></div>
                  <div className="flex-1">
                    <div className="text-charcoal font-semibold text-sm">{m.marca} {m.modelo}</div>
                    <div className="text-gray-text text-xs">{[m.ano, m.cor].filter(Boolean).join(" · ") || "—"} · {m.km_atual.toLocaleString("pt-BR")} km</div>
                  </div>
                  <span className="px-2 py-0.5 rounded text-xs" style={{ background: "#F0EDE5", color: "#777A78", fontFamily: "var(--font-mono)" }}>{formatarPlaca(m.placa)}</span>
                </div>
              ))}
              {cliente.motos.length === 0 && <div className="text-gray-text text-sm text-center py-6">Nenhuma moto cadastrada.</div>}
              <Button variant="secondary" size="sm" onClick={onNovaMoto}><Plus size={12} /> Adicionar moto</Button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default function ClientesIndex({ clientes }: { clientes: Cliente[] }) {
  const [busca, setBusca] = useState("");
  const [abertoId, setAbertoId] = useState<number | null>(null);
  const [form, setForm] = useState<"novo" | "editar" | "moto" | null>(null);

  const aberto = clientes.find((c) => c.id === abertoId) ?? null; // sempre a versão mais nova vinda do servidor
  const q = busca.toLowerCase().replace(/\D/g, "") || busca.toLowerCase();
  const filtrados = clientes.filter((c) =>
    !busca || c.nome.toLowerCase().includes(busca.toLowerCase()) || (c.telefone ?? "").includes(q) || (c.cpf_cnpj ?? "").includes(q),
  );

  return (
    <AppLayout title="Clientes">
      <PageHeader title="Clientes" subtitle="Cadastro de clientes e suas motocicletas.">
        <Button variant="primary" onClick={() => setForm("novo")}><Plus size={14} /> Novo cliente</Button>
      </PageHeader>

      <div className="flex items-center gap-3 mb-5">
        <Input className="w-72" placeholder="Buscar por nome, telefone ou CPF..." value={busca} onChange={setBusca} prefix={<Search size={14} />} />
        <span className="text-gray-text text-sm ml-auto">{filtrados.length} clientes</span>
      </div>

      <Card>
        <Table>
          <thead>
            <tr><Th>Cliente</Th><Th>Telefone</Th><Th>CPF / CNPJ</Th><Th className="text-right">Motos</Th><Th></Th></tr>
          </thead>
          <tbody>
            {filtrados.map((c) => (
              <tr key={c.id} className="hover:bg-cream/40 transition-fast cursor-pointer" onClick={() => setAbertoId(c.id)}>
                <Td><span className="font-medium text-charcoal">{c.nome}</span></Td>
                <Td><span className="flex items-center gap-1.5 text-gray-text text-sm"><Phone size={12} />{formatarTelefone(c.telefone)}</span></Td>
                <Td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "#777A78" }}>{formatarDoc(c.cpf_cnpj)}</span></Td>
                <Td className="text-right"><span className="inline-flex items-center gap-1 text-gray-text text-sm"><Bike size={12} />{c.motos.length}</span></Td>
                <Td><ChevronRight size={14} className="text-gray-text" /></Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {filtrados.length === 0 && <div className="py-12 text-center text-gray-text text-sm">Nenhum cliente encontrado.</div>}
      </Card>

      {aberto && !form && (
        <Detalhe cliente={aberto} onClose={() => setAbertoId(null)} onEditar={() => setForm("editar")} onNovaMoto={() => setForm("moto")} />
      )}

      {form === "novo" && (
        <Modal title="Novo cliente" onClose={() => setForm(null)} width="max-w-2xl"><ClienteForm onClose={() => setForm(null)} /></Modal>
      )}
      {form === "editar" && aberto && (
        <Modal title="Editar cliente" onClose={() => setForm(null)} width="max-w-2xl"><ClienteForm cliente={aberto} onClose={() => setForm(null)} /></Modal>
      )}
      {form === "moto" && aberto && (
        <Modal title={`Nova moto de ${aberto.nome}`} onClose={() => setForm(null)} width="max-w-2xl">
          <MotoForm clientes={[]} clienteFixo={aberto.id} onClose={() => setForm(null)} />
        </Modal>
      )}
    </AppLayout>
  );
}