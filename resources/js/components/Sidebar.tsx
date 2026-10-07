import { Link, usePage } from "@inertiajs/react";
import {
  LayoutDashboard, ShoppingCart, Package, Boxes, ArrowDownUp,
  Wrench, Users, Bike, FileText, BarChart2, Settings, LogOut, Bell, ChevronRight,
} from "lucide-react";
import type { ElementType } from "react";

type Item = { href: string; label: string; icon: ElementType };

const nav: Item[] = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/vendas", label: "Vendas", icon: ShoppingCart },
  { href: "/produtos", label: "Produtos", icon: Package },
  { href: "/estoque", label: "Estoque", icon: Boxes },
  { href: "/entradas", label: "Entradas", icon: ArrowDownUp },
  { href: "/servicos", label: "Serviços", icon: Wrench },
  { href: "/clientes", label: "Clientes", icon: Users },
  { href: "/motocicletas", label: "Motocicletas", icon: Bike },
  { href: "/fiscal", label: "Fiscal", icon: FileText },
  { href: "/relatorios", label: "Relatórios", icon: BarChart2 },
];

const extras: Item[] = [
  { href: "/configuracoes", label: "Configurações", icon: Settings },
  { href: "/notificacoes", label: "Notificações", icon: Bell },
];

function NavLink({ item, active, onClick }: { item: Item; active: boolean; onClick: () => void }) {
  const Icon = item.icon;
  return (
    <Link
      href={item.href}
      onClick={onClick}
      className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-md mb-0.5 text-left group transition-fast ${
        active ? "bg-red text-white" : "text-gray-muted hover:text-white hover:bg-graphite-2"
      }`}
      style={{ fontSize: 13.5, fontWeight: active ? 500 : 400 }}
    >
      <Icon size={15} className={active ? "text-white" : "text-gray-text group-hover:text-white transition-fast"} />
      <span>{item.label}</span>
      {active && <ChevronRight size={12} className="ml-auto opacity-60" />}
    </Link>
  );
}

export default function Sidebar({ mobileOpen, onMobileClose }: { mobileOpen: boolean; onMobileClose: () => void }) {
  const { url } = usePage();
  const { auth } = usePage<{ auth: { user: { name: string; perfil: string } | null } }>().props;
  const nome = auth.user?.name ?? "";
  const perfis: Record<string, string> = { administrador: "Administrador", balconista: "Balconista", mecanico: "Mecânico" };
  const isActive = (href: string) => url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);

  return (
    <>
      {mobileOpen && <div className="fixed inset-0 bg-black/60 z-30 lg:hidden" onClick={onMobileClose} />}

      <aside
        className={`fixed top-0 left-0 h-full w-60 bg-charcoal flex flex-col z-40 transition-transform duration-300 ease-in-out ${
          mobileOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0"
        }`}
        style={{ borderRight: "1px solid rgba(255,255,255,0.06)" }}
      >
        <div className="px-5 pt-6 pb-5" style={{ borderBottom: "1px solid rgba(255,255,255,0.06)" }}>
          <div className="flex items-center gap-3">
            <div
              className="w-8 h-8 rounded flex items-center justify-center flex-shrink-0 text-white font-bold"
              style={{ background: "#C9281F", fontFamily: "var(--font-condensed)", fontSize: 14 }}
            >
              {nome.charAt(0).toUpperCase()}
            </div>
            <div>
              <div className="text-white leading-none" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 17, letterSpacing: "0.06em" }}>
                GESTOQUE
              </div>
              <div className="mt-0.5" style={{ color: "#777A78", fontSize: 10 }}>Lupim Moto Mecânica</div>
            </div>
          </div>
        </div>

        <nav className="flex-1 overflow-y-auto sidebar-scroll py-3 px-2.5">
          {nav.map((item) => (
            <NavLink key={item.href} item={item} active={isActive(item.href)} onClick={onMobileClose} />
          ))}
          <div className="my-3 mx-1" style={{ borderTop: "1px solid rgba(255,255,255,0.06)" }} />
          {extras.map((item) => (
            <NavLink key={item.href} item={item} active={isActive(item.href)} onClick={onMobileClose} />
          ))}
        </nav>

        <div className="px-4 py-4" style={{ borderTop: "1px solid rgba(255,255,255,0.06)" }}>
          <div className="flex items-center gap-3">
            <div
              className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-sm font-semibold"
              style={{ background: "#C9281F", fontFamily: "var(--font-condensed)" }}
            >
              {nome.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-white text-sm font-medium truncate">{nome}</div>
              <div className="text-xs truncate" style={{ color: "#777A78" }}>{perfis[auth.user?.perfil ?? ""] ?? ""}</div>
            </div>
            <Link href="/logout" method="post" as="button" className="text-gray-text hover:text-white transition-fast" title="Sair">
              <LogOut size={14} />
            </Link>
          </div>
        </div>
      </aside>
    </>
  );
}