import { useForm } from "@inertiajs/react";
import type { FormEvent, ReactNode } from "react";
import { Button, Input, Select } from "@/components/ui";

export type Moto = { id: number; placa: string; marca: string; modelo: string; ano: number | null; cor: string | null; km_atual: number };
export type Cliente = {
  id: number; nome: string; cpf_cnpj: string | null; telefone: string | null; email: string | null;
  endereco: string | null; observacoes: string | null; motos: Moto[];
};

export function formatarDoc(v: string | null) {
  if (!v) return "—";
  return v.length === 11
    ? v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4")
    : v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
}
export function formatarTelefone(v: string | null) {
  if (!v) return "—";
  return v.length === 11 ? v.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3") : v.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
}
export function formatarPlaca(p: string) {
  return /^[A-Z]{3}\d{4}$/.test(p) ? `${p.slice(0, 3)}-${p.slice(3)}` : p;
}

function Campo({ label, erro, children, className = "" }: { label: string; erro?: string; children: ReactNode; className?: string }) {
  return (
    <div className={className}>
      <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>{label}</label>
      {children}
      {erro && <p className="text-red text-xs mt-1">{erro}</p>}
    </div>
  );
}

export function ClienteForm({ cliente, onClose }: { cliente?: Cliente; onClose: () => void }) {
  const { data, setData, post, put, processing, errors } = useForm({
    nome: cliente?.nome ?? "",
    cpf_cnpj: cliente?.cpf_cnpj ?? "",
    telefone: cliente?.telefone ?? "",
    email: cliente?.email ?? "",
    endereco: cliente?.endereco ?? "",
    observacoes: cliente?.observacoes ?? "",
  });

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    const opcoes = { preserveScroll: true, onSuccess: () => onClose() };
    cliente ? put(`/clientes/${cliente.id}`, opcoes) : post("/clientes", opcoes);
  };

  return (
    <form onSubmit={salvar} className="space-y-4">
      <Campo label="Nome *" erro={errors.nome}>
        <Input value={data.nome} onChange={(v) => setData("nome", v)} placeholder="Nome completo ou razão social" />
      </Campo>
      <div className="grid grid-cols-2 gap-4">
        <Campo label="CPF / CNPJ" erro={errors.cpf_cnpj}>
          <Input value={data.cpf_cnpj} onChange={(v) => setData("cpf_cnpj", v)} placeholder="Somente números ou com pontuação" />
        </Campo>
        <Campo label="Telefone / WhatsApp" erro={errors.telefone}>
          <Input value={data.telefone} onChange={(v) => setData("telefone", v)} placeholder="(54) 99999-9999" />
        </Campo>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <Campo label="E-mail" erro={errors.email}>
          <Input value={data.email} onChange={(v) => setData("email", v)} placeholder="cliente@email.com" />
        </Campo>
        <Campo label="Endereço" erro={errors.endereco}>
          <Input value={data.endereco} onChange={(v) => setData("endereco", v)} placeholder="Rua, número, cidade" />
        </Campo>
      </div>
      <Campo label="Observações" erro={errors.observacoes}>
        <textarea
          value={data.observacoes}
          onChange={(e) => setData("observacoes", e.target.value)}
          rows={2}
          className="w-full px-3 py-2 rounded-md border text-charcoal text-sm focus:outline-none focus:ring-1 focus:ring-red resize-none"
          style={{ borderColor: "#D8D4CC" }}
        />
      </Campo>
      <div className="flex justify-end gap-3 pt-2" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing}>{processing ? "Salvando..." : "Salvar cliente"}</Button>
      </div>
    </form>
  );
}

export function MotoForm({ clientes, clienteFixo, onClose }: { clientes: { id: number; nome: string }[]; clienteFixo?: number; onClose: () => void }) {
  const { data, setData, post, processing, errors } = useForm({
    cliente_id: clienteFixo ? String(clienteFixo) : "",
    placa: "",
    marca: "",
    modelo: "",
    ano: "",
    cor: "",
    km_atual: "",
  });

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    post("/motocicletas", { preserveScroll: true, onSuccess: () => onClose() });
  };

  return (
    <form onSubmit={salvar} className="space-y-4">
      {!clienteFixo && (
        <Campo label="Proprietário *" erro={errors.cliente_id}>
          <Select value={data.cliente_id} onChange={(v) => setData("cliente_id", v)} className="w-full">
            <option value="">Selecionar cliente...</option>
            {clientes.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
          </Select>
        </Campo>
      )}
      <div className="grid grid-cols-3 gap-4">
        <Campo label="Placa *" erro={errors.placa}>
          <Input value={data.placa} onChange={(v) => setData("placa", v.toUpperCase())} placeholder="ABC1D23" />
        </Campo>
        <Campo label="Marca *" erro={errors.marca}>
          <Input value={data.marca} onChange={(v) => setData("marca", v)} placeholder="Honda" />
        </Campo>
        <Campo label="Modelo *" erro={errors.modelo}>
          <Input value={data.modelo} onChange={(v) => setData("modelo", v)} placeholder="CG 160 Titan" />
        </Campo>
      </div>
      <div className="grid grid-cols-3 gap-4">
        <Campo label="Ano" erro={errors.ano}>
          <Input value={data.ano} onChange={(v) => setData("ano", v)} placeholder="2022" />
        </Campo>
        <Campo label="Cor" erro={errors.cor}>
          <Input value={data.cor} onChange={(v) => setData("cor", v)} placeholder="Vermelha" />
        </Campo>
        <Campo label="Km atual" erro={errors.km_atual}>
          <Input value={data.km_atual} onChange={(v) => setData("km_atual", v)} placeholder="38.420" />
        </Campo>
      </div>
      <div className="flex justify-end gap-3 pt-2" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing}>{processing ? "Salvando..." : "Salvar moto"}</Button>
      </div>
    </form>
  );
}