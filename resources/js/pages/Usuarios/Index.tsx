import { useForm } from "@inertiajs/react";
import { useState, type FormEvent, type ReactNode } from "react";
import { Plus, Pencil, UserCog } from "lucide-react";
import AppLayout from "@/layouts/AppLayout";
import { PageHeader, Button, Input, Select, Card, Table, Th, Td, Modal, Badge, EmptyState } from "@/components/ui";
import { useUsuario } from "@/components/Marca";

type Usuario = {
  id: number;
  name: string;
  email: string;
  perfil: string;
  ativo: boolean;
  criado_em: string | null;
};

type Props = {
  usuarios: Usuario[];
  perfis: Record<string, string>;
};

function Campo({ label, erro, children }: { label: string; erro?: string; children: ReactNode }) {
  return (
    <div>
      <label className="block text-charcoal text-xs font-medium mb-1.5">{label}</label>
      {children}
      {erro && <p className="text-red text-xs mt-1">{erro}</p>}
    </div>
  );
}

function UsuarioForm({ usuario, perfis, proprio, onClose }: { usuario?: Usuario; perfis: Record<string, string>; proprio: boolean; onClose: () => void }) {
  const { data, setData, post, put, processing, errors } = useForm({
    name: usuario?.name ?? "",
    email: usuario?.email ?? "",
    perfil: usuario?.perfil ?? "mecanico",
    ativo: usuario?.ativo ?? true,
    password: "",
    password_confirmation: "",
  });

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    const opcoes = { preserveScroll: true, onSuccess: () => onClose() };
    usuario ? put(`/usuarios/${usuario.id}`, opcoes) : post("/usuarios", opcoes);
  };

  return (
    <form onSubmit={salvar} className="space-y-4">
      <Campo label="Nome *" erro={errors.name}>
        <Input value={data.name} onChange={(v) => setData("name", v)} placeholder="Nome de quem vai usar a conta" />
      </Campo>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Campo label="E-mail (login) *" erro={errors.email}>
          <Input type="email" value={data.email} onChange={(v) => setData("email", v)} placeholder="pessoa@email.com" />
        </Campo>
        <Campo label="Perfil *" erro={errors.perfil}>
          {/* A pessoa não pode tirar o próprio perfil de administrador. */}
          <Select className="w-full" value={data.perfil} onChange={(v) => setData("perfil", v)} disabled={proprio}>
            {Object.entries(perfis).map(([valor, rotulo]) => (
              <option key={valor} value={valor}>{rotulo}</option>
            ))}
          </Select>
        </Campo>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Campo label={usuario ? "Nova senha" : "Senha *"} erro={errors.password}>
          <Input
            type="password"
            value={data.password}
            onChange={(v) => setData("password", v)}
            placeholder={usuario ? "Deixe vazio para manter" : "Mínimo 8 caracteres"}
          />
        </Campo>
        <Campo label={usuario ? "Repetir nova senha" : "Repetir senha *"}>
          <Input type="password" value={data.password_confirmation} onChange={(v) => setData("password_confirmation", v)} />
        </Campo>
      </div>
      {usuario && (
        <Campo label="Situação" erro={errors.ativo}>
          <label className="flex items-center gap-2 text-sm text-charcoal">
            <input
              type="checkbox"
              checked={data.ativo}
              disabled={proprio}
              onChange={(e) => setData("ativo", e.target.checked)}
              className="accent-[#C9281F]"
            />
            Conta ativa (desmarque para bloquear o acesso)
          </label>
          {proprio && <p className="text-gray-text text-xs mt-1">Esta é a sua conta: perfil e situação não podem ser alterados por você.</p>}
        </Campo>
      )}
      <div className="flex justify-end gap-3 pt-2" style={{ borderTop: "1px solid #F0EDE5" }}>
        <Button variant="secondary" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" type="submit" disabled={processing}>
          {processing ? "Salvando..." : usuario ? "Salvar alterações" : "Criar conta"}
        </Button>
      </div>
    </form>
  );
}

export default function UsuariosIndex({ usuarios, perfis }: Props) {
  const eu = useUsuario();
  const [form, setForm] = useState<"novo" | number | null>(null);
  const editando = typeof form === "number" ? usuarios.find((u) => u.id === form) : undefined;

  return (
    <AppLayout title="Usuários">
      <PageHeader title="Usuários" subtitle="Contas de acesso ao sistema. Contas desativadas não conseguem entrar.">
        <Button variant="primary" onClick={() => setForm("novo")}><Plus size={14} /> Novo usuário</Button>
      </PageHeader>

      <Card>
        <Table>
          <thead>
            <tr><Th>Nome</Th><Th soDesktop>E-mail</Th><Th soDesktop>Perfil</Th><Th>Situação</Th><Th soDesktop>Criado em</Th><Th></Th></tr>
          </thead>
          <tbody>
            {usuarios.map((u) => (
              <tr key={u.id} className={u.ativo ? "" : "opacity-60"}>
                <Td>
                  <span className="font-medium text-charcoal">{u.name}</span>
                  {u.id === eu?.id && <span className="text-gray-text text-xs ml-2">(você)</span>}
                  {/* No celular, e-mail e perfil aparecem aqui embaixo do nome. */}
                  <div className="md:hidden mt-1 space-y-1">
                    <div className="text-gray-text text-xs break-all">{u.email}</div>
                    <Badge variant={u.perfil === "administrador" ? "purple" : "neutral"}>{perfis[u.perfil] ?? u.perfil}</Badge>
                  </div>
                </Td>
                <Td soDesktop><span className="text-gray-text text-sm">{u.email}</span></Td>
                <Td soDesktop><Badge variant={u.perfil === "administrador" ? "purple" : "neutral"}>{perfis[u.perfil] ?? u.perfil}</Badge></Td>
                <Td><Badge variant={u.ativo ? "success" : "danger"}>{u.ativo ? "Ativa" : "Desativada"}</Badge></Td>
                <Td soDesktop><span className="text-gray-text text-sm">{u.criado_em ?? "—"}</span></Td>
                <Td className="text-right">
                  <Button variant="ghost" size="sm" onClick={() => setForm(u.id)}>
                    <Pencil size={12} /> <span className="hidden sm:inline">Editar</span>
                  </Button>
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {usuarios.length === 0 && <EmptyState icon={UserCog} title="Nenhum usuário" />}
      </Card>

      {form === "novo" && (
        <Modal title="Novo usuário" onClose={() => setForm(null)} width="max-w-2xl">
          <UsuarioForm perfis={perfis} proprio={false} onClose={() => setForm(null)} />
        </Modal>
      )}
      {editando && (
        <Modal title={`Editar ${editando.name}`} onClose={() => setForm(null)} width="max-w-2xl">
          <UsuarioForm usuario={editando} perfis={perfis} proprio={editando.id === eu?.id} onClose={() => setForm(null)} />
        </Modal>
      )}
    </AppLayout>
  );
}
