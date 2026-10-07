import { Head, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import { Input } from "@/components/ui";

export default function Login() {
  const { data, setData, post, processing, errors } = useForm({ email: "", password: "", lembrar: false });

  const entrar = (e: FormEvent) => {
    e.preventDefault();
    post("/login", { onFinish: () => setData("password", "") });
  };

  return (
    <>
      <Head title="Entrar" />
      <div className="min-h-screen flex items-center justify-center bg-charcoal px-4">
        <div className="w-full max-w-sm">
          <div className="flex items-center gap-3 mb-8 justify-center">
            <div className="w-10 h-10 rounded flex items-center justify-center text-white font-bold bg-red" style={{ fontFamily: "var(--font-condensed)", fontSize: 18 }}>
              G
            </div>
            <div>
              <div className="text-white leading-none" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 24, letterSpacing: "0.06em" }}>GESTOQUE</div>
              <div style={{ color: "#777A78", fontSize: 11 }}>Lupim Moto Mecânica</div>
            </div>
          </div>

          <form onSubmit={entrar} className="bg-white rounded-xl p-7 space-y-4 shadow-2xl">
            <div>
              <div className="text-charcoal" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 22 }}>Entrar</div>
              <div className="text-gray-text text-sm">Acesse com seu e-mail e senha.</div>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>E-mail</label>
              <Input type="email" placeholder="voce@oficina.com" value={data.email} onChange={(v) => setData("email", v)} />
              {errors.email && <p className="text-red text-xs mt-1">{errors.email}</p>}
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-text mb-1.5 uppercase" style={{ letterSpacing: "0.06em" }}>Senha</label>
              <Input type="password" placeholder="••••••••" value={data.password} onChange={(v) => setData("password", v)} />
              {errors.password && <p className="text-red text-xs mt-1">{errors.password}</p>}
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-text cursor-pointer">
              <input type="checkbox" checked={data.lembrar} onChange={(e) => setData("lembrar", e.target.checked)} className="accent-red" />
              Manter conectado
            </label>

            <button
              type="submit"
              disabled={processing}
              className="w-full py-3 rounded-lg font-bold text-white bg-red hover:bg-red-dark transition-fast disabled:opacity-50"
              style={{ fontFamily: "var(--font-condensed)", fontSize: 16, letterSpacing: "0.05em" }}
            >
              {processing ? "ENTRANDO..." : "ENTRAR"}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}