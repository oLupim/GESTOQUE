import type { ElementType, ReactNode } from "react";

export function Badge({ variant, children }: { variant: "success" | "warning" | "danger" | "neutral" | "info" | "purple"; children: ReactNode }) {
  const styles: Record<string, string> = {
    success: "text-green-700 bg-green-50 border-green-200",
    warning: "text-orange-700 bg-orange-50 border-orange-200",
    danger: "text-red-700 bg-red-100 border-red-200",
    neutral: "text-gray-600 bg-gray-100 border-gray-200",
    info: "text-blue-700 bg-blue-50 border-blue-200",
    purple: "text-purple-700 bg-purple-50 border-purple-200",
  };
  return (
    <span
      className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${styles[variant]}`}
      style={{ fontFamily: "var(--font-sans)" }}
    >
      {children}
    </span>
  );
}

export function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { variant: "success" | "warning" | "danger" | "neutral" | "info" | "purple"; label: string }> = {
    "Normal": { variant: "success", label: "Normal" },
    "Baixo": { variant: "warning", label: "Baixo" },
    "Crítico": { variant: "danger", label: "Crítico" },
    "Sem estoque": { variant: "neutral", label: "Sem estoque" },
    "Finalizado": { variant: "success", label: "Finalizado" },
    "Entregue": { variant: "success", label: "Entregue" },
    "Em andamento": { variant: "info", label: "Em andamento" },
    "Aguardando peça": { variant: "warning", label: "Aguardando peça" },
    "Aberto": { variant: "neutral", label: "Aberto" },
    "Cancelado": { variant: "danger", label: "Cancelado" },
    "Concluída": { variant: "success", label: "Concluída" },
    "Autorizada": { variant: "success", label: "Autorizada" },
    "Rejeitada": { variant: "danger", label: "Rejeitada" },
    "Processando": { variant: "info", label: "Processando" },
    "Pendente": { variant: "warning", label: "Pendente" },
    "Cancelada": { variant: "neutral", label: "Cancelada" },
  };
  const cfg = map[status] || { variant: "neutral" as const, label: status };
  return <Badge variant={cfg.variant}>{cfg.label}</Badge>;
}

export function Card({ children, className = "", onClick }: { children: ReactNode; className?: string; onClick?: () => void }) {
  return (
    <div
      className={`bg-white rounded-lg border ${className}`}
      style={{ borderColor: "#E5E2DA" }}
      onClick={onClick}
    >
      {children}
    </div>
  );
}

export function PageHeader({ title, subtitle, children }: { title: string; subtitle?: string; children?: ReactNode }) {
  return (
    <div className="flex items-start justify-between mb-6">
      <div>
        <h1
          className="text-charcoal leading-tight"
          style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 26, letterSpacing: "0.01em" }}
        >
          {title}
        </h1>
        {subtitle && (
          <p className="text-gray-text mt-0.5" style={{ fontSize: 13.5 }}>
            {subtitle}
          </p>
        )}
      </div>
      {children && <div className="flex items-center gap-2">{children}</div>}
    </div>
  );
}

export function Button({
  variant = "primary",
  size = "md",
  children,
  onClick,
  type = "button",
  disabled,
  className = "",
}: {
  variant?: "primary" | "secondary" | "ghost" | "danger";
  size?: "sm" | "md" | "lg";
  children: ReactNode;
  onClick?: () => void;
  type?: "button" | "submit";
  disabled?: boolean;
  className?: string;
}) {
  const base = "inline-flex items-center gap-2 font-medium rounded-md transition-fast cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed";
  const variants = {
    primary: "bg-red text-white hover:bg-red-dark",
    secondary: "bg-white text-charcoal border hover:bg-cream",
    ghost: "text-gray-text hover:text-charcoal hover:bg-cream",
    danger: "bg-red/10 text-red hover:bg-red/20",
  };
  const sizes = {
    sm: "px-3 py-1.5 text-xs",
    md: "px-4 py-2 text-sm",
    lg: "px-5 py-2.5 text-sm",
  };
  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled}
      className={`${base} ${variants[variant]} ${sizes[size]} ${className}`}
      style={{ borderColor: variant === "secondary" ? "#E5E2DA" : undefined, fontFamily: "var(--font-sans)" }}
    >
      {children}
    </button>
  );
}

export function Input({
  placeholder,
  value,
  onChange,
  className = "",
  prefix,
  type = "text",
}: {
  placeholder?: string;
  value?: string;
  onChange?: (v: string) => void;
  className?: string;
  prefix?: ReactNode;
  type?: string;
}) {
  return (
    <div className={`relative flex items-center ${className}`}>
      {prefix && (
        <div className="absolute left-3 text-gray-text pointer-events-none">{prefix}</div>
      )}
      <input
        type={type}
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange?.(e.target.value)}
        className={`w-full bg-white border rounded-md text-charcoal placeholder-gray-text focus:outline-none focus:ring-1 focus:ring-red focus:border-red transition-fast ${prefix ? "pl-9" : "pl-3"} pr-3 py-2 text-sm`}
        style={{ borderColor: "#D8D4CC", fontFamily: "var(--font-sans)" }}
      />
    </div>
  );
}

export function Select({
  value,
  onChange,
  children,
  className = "",
  disabled,
}: {
  value?: string;
  onChange?: (v: string) => void;
  children: ReactNode;
  className?: string;
  disabled?: boolean;
}) {
  return (
    <select
      value={value}
      onChange={(e) => onChange?.(e.target.value)}
      disabled={disabled}
      className={`bg-white border rounded-md text-charcoal text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red transition-fast cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ${className}`}
      style={{ borderColor: "#D8D4CC", fontFamily: "var(--font-sans)", color: "#171918" }}
    >
      {children}
    </select>
  );
}

export function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  trend,
  variant = "default",
  onClick,
}: {
  label: string;
  value: string;
  sub?: string;
  icon?: ElementType;
  trend?: { value: string; up: boolean };
  variant?: "default" | "warning" | "danger" | "success";
  onClick?: () => void;
}) {
  const iconBg: Record<string, string> = {
    default: "bg-red-muted",
    warning: "bg-orange-muted",
    danger: "bg-red-muted",
    success: "bg-green-muted",
  };
  const iconColor: Record<string, string> = {
    default: "#C9281F",
    warning: "#D4610D",
    danger: "#C9281F",
    success: "#1A7C4E",
  };
  return (
    <Card className={`p-5 ${onClick ? "cursor-pointer hover:shadow-sm transition-fast" : ""}`} onClick={onClick}>
      <div className="flex items-start justify-between">
        <div>
          <div className="text-gray-text text-xs font-medium mb-2 uppercase tracking-wide" style={{ fontFamily: "var(--font-sans)", letterSpacing: "0.07em", fontSize: 11 }}>
            {label}
          </div>
          <div
            className="text-charcoal leading-none"
            style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 28 }}
          >
            {value}
          </div>
          {sub && (
            <div className="text-gray-text mt-1.5" style={{ fontSize: 12 }}>
              {sub}
            </div>
          )}
          {trend && (
            <div
              className={`flex items-center gap-1 mt-2 text-xs font-medium`}
              style={{ color: trend.up ? "#1A7C4E" : "#C9281F" }}
            >
              <span>{trend.up ? "↑" : "↓"}</span>
              <span>{trend.value}</span>
            </div>
          )}
        </div>
        {Icon && (
          <div className={`w-9 h-9 rounded-md flex items-center justify-center flex-shrink-0 ${iconBg[variant]}`}>
            <Icon size={18} style={{ color: iconColor[variant] }} />
          </div>
        )}
      </div>
    </Card>
  );
}

export function Table({ children, className = "" }: { children: ReactNode; className?: string }) {
  return (
    <div className={`overflow-x-auto ${className}`}>
      <table className="w-full text-sm" style={{ fontFamily: "var(--font-sans)" }}>
        {children}
      </table>
    </div>
  );
}

export function Th({ children, className = "" }: { children?: ReactNode; className?: string }) {
  return (
    <th
      className={`px-4 py-3 text-left text-gray-text font-medium ${className}`}
      style={{ fontSize: 11.5, letterSpacing: "0.05em", borderBottom: "1px solid #E5E2DA", background: "#F9F7F3" }}
    >
      {children}
    </th>
  );
}

export function Td({ children, className = "" }: { children?: ReactNode; className?: string }) {
  return (
    <td
      className={`px-4 py-3.5 text-charcoal ${className}`}
      style={{ fontSize: 13.5, borderBottom: "1px solid #F0EDE5" }}
    >
      {children}
    </td>
  );
}

export function Tabs({
  tabs,
  active,
  onChange,
}: {
  tabs: { id: string; label: string; count?: number }[];
  active: string;
  onChange: (id: string) => void;
}) {
  return (
    <div className="flex gap-0 mb-5" style={{ borderBottom: "1px solid #E5E2DA" }}>
      {tabs.map((tab) => (
        <button
          key={tab.id}
          onClick={() => onChange(tab.id)}
          className={`px-4 py-2.5 text-sm font-medium transition-fast relative flex items-center gap-2 ${
            active === tab.id
              ? "text-red border-b-2 border-red"
              : "text-gray-text hover:text-charcoal"
          }`}
          style={{ fontFamily: "var(--font-sans)", marginBottom: -1 }}
        >
          {tab.label}
          {tab.count !== undefined && (
            <span
              className={`rounded-full text-xs px-1.5 py-0.5 ${active === tab.id ? "bg-red/10 text-red" : "bg-gray-100 text-gray-500"}`}
              style={{ fontSize: 11 }}
            >
              {tab.count}
            </span>
          )}
        </button>
      ))}
    </div>
  );
}

export function Modal({ title, onClose, children, width = "max-w-lg" }: { title: string; onClose: () => void; children: ReactNode; width?: string }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className={`bg-white rounded-xl shadow-2xl w-full ${width} max-h-[90vh] overflow-y-auto`} style={{ border: "1px solid #E5E2DA" }}>
        <div className="flex items-center justify-between px-6 py-4" style={{ borderBottom: "1px solid #F0EDE5" }}>
          <h2 className="text-charcoal font-semibold" style={{ fontFamily: "var(--font-condensed)", fontWeight: 700, fontSize: 18 }}>
            {title}
          </h2>
          <button onClick={onClose} className="text-gray-text hover:text-charcoal transition-fast">
            <span className="text-xl leading-none">×</span>
          </button>
        </div>
        <div className="px-6 py-5">{children}</div>
      </div>
    </div>
  );
}

export function EmptyState({ icon: Icon, title, subtitle }: { icon: ElementType; title: string; subtitle?: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <div className="w-12 h-12 rounded-full flex items-center justify-center mb-3" style={{ background: "#F0EDE5" }}>
        <Icon size={22} style={{ color: "#C9281F" }} />
      </div>
      <div className="text-charcoal font-semibold mb-1" style={{ fontFamily: "var(--font-display)" }}>{title}</div>
      {subtitle && <div className="text-gray-text text-sm">{subtitle}</div>}
    </div>
  );
}
