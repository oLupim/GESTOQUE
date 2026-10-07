import AppLayout from "@/layouts/AppLayout";

const nomes: Record<string, string> = {
  dashboard: "Dashboard", vendas: "Vendas", estoque: "Estoque", entradas: "Entradas",
  servicos: "Serviços", clientes: "Clientes", motocicletas: "Motocicletas", fiscal: "Fiscal",
  relatorios: "Relatórios", configuracoes: "Configurações", notificacoes: "Notificações",
};

export default function EmConstrucao({ modulo }: { modulo: string }) {
  const titulo = nomes[modulo] ?? modulo;
  return (
    <AppLayout title={titulo}>
      <div className="flex flex-col items-center justify-center py-24">
        <div className="w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style={{ background: "#F0EDE5" }}>
          <span style={{ fontSize: 24 }}>🛠</span>
        </div>
        <div className="text-charcoal mb-2" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 22 }}>
          {titulo}
        </div>
        <div className="text-gray-text" style={{ fontSize: 14 }}>Em construção.</div>
      </div>
    </AppLayout>
  );
}