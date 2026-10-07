import { Head, Link } from "@inertiajs/react";
import { useState, type ReactNode } from "react";
import { Menu, Bell, Plus, Wrench } from "lucide-react";
import Sidebar from "@/components/Sidebar";

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <>
      <Head title={title} />
      <div className="flex h-full min-h-screen bg-cream">
        <Sidebar mobileOpen={mobileOpen} onMobileClose={() => setMobileOpen(false)} />

        <div className="flex-1 flex flex-col min-w-0 lg:ml-60">
          <header
            className="sticky top-0 z-20 flex items-center justify-between px-5 py-3.5 bg-white/90 backdrop-blur-sm"
            style={{ borderBottom: "1px solid #E5E2DA" }}
          >
            <div className="flex items-center gap-3">
              <button className="lg:hidden p-1.5 rounded-md text-gray-text hover:bg-cream transition-fast" onClick={() => setMobileOpen(true)}>
                <Menu size={18} />
              </button>
              <div className="flex items-center gap-2">
                <span className="text-gray-text text-sm hidden sm:block">Gestoque</span>
                <span className="text-gray-line text-sm hidden sm:block">/</span>
                <span className="text-charcoal font-semibold text-sm" style={{ fontFamily: "var(--font-display)" }}>
                  {title}
                </span>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Link
                href="/servicos"
                className="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-md text-gray-text hover:text-charcoal hover:bg-cream transition-fast text-xs font-medium"
                style={{ border: "1px solid #E5E2DA" }}
              >
                <Wrench size={12} /> Serviço
              </Link>
              <Link
                href="/vendas"
                className="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-md text-white transition-fast text-xs font-medium bg-red hover:bg-red-dark"
              >
                <Plus size={12} /> Venda
              </Link>
              <Link href="/notificacoes" className="relative p-2 rounded-md text-gray-text hover:text-charcoal hover:bg-cream transition-fast">
                <Bell size={16} />
              </Link>
              <div
                className="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold"
                style={{ background: "#C9281F", fontFamily: "var(--font-condensed)" }}
              >
                G
              </div>
            </div>
          </header>

          <main className="flex-1 p-5 xl:p-7 max-w-screen-2xl w-full mx-auto">{children}</main>
        </div>
      </div>
    </>
  );
}
