import {
  BadgeCheck,
  CalendarHeart,
  CalendarPlus,
  FileText,
  Globe,
  GraduationCap,
  KeyRound,
  LayoutDashboard,
  LogOut,
  Settings,
  ShieldAlert,
  UserPlus,
  Users,
} from "lucide-react";
import { Link } from "react-router-dom";
import { clearSession, getSession } from "../../auth";
import { menuItems } from "../data/adminData";

const SYSTEM_MANAGEMENT = {
  label: "System Management",
  path: "/admin/system-management",
};

const ICONS = {
  "/admin": LayoutDashboard,
  "/admin/profiles": Users,
  "/admin/nri-profiles": Globe,
  "/admin/verified-profiles": BadgeCheck,
  "/admin/unverified-profiles": ShieldAlert,
  "/admin/melava": CalendarHeart,
  "/admin/melava-campaigns": CalendarPlus,
  "/admin/workshop": GraduationCap,
  "/admin/requests": UserPlus,
  "/admin/reset-passwords": KeyRound,
  "/admin/report": FileText,
  "/admin/system-management": Settings,
};

const ICON_COLORS = {
  "/admin": "#2f80ed",
  "/admin/profiles": "#8b5cf6",
  "/admin/nri-profiles": "#14b8a6",
  "/admin/verified-profiles": "#22c55e",
  "/admin/unverified-profiles": "#f97316",
  "/admin/melava": "#ec4899",
  "/admin/melava-campaigns": "#e11d48",
  "/admin/workshop": "#7c3aed",
  "/admin/requests": "#0ea5e9",
  "/admin/reset-passwords": "#f59e0b",
  "/admin/report": "#6366f1",
  "/admin/system-management": "#0f766e",
};

const isSuperAdmin = () => {
  const role = getSession()?.user?.role;
  const normalizedName = String(role?.name || "").toLowerCase().replace(/[^a-z]/g, "");
  return normalizedName === "superadmin" && String(role?.user_type) === "00";
};

export default function Sidebar({ currentPath, onNavigate }) {
  const items = isSuperAdmin() ? [...menuItems, SYSTEM_MANAGEMENT] : menuItems;
  const activePath = items.reduce((active, item) => {
    const matches =
      currentPath === item.path ||
      (item.path !== "/admin" && currentPath.startsWith(`${item.path}/`));
    return matches && item.path.length > active.length ? item.path : active;
  }, "");

  return (
    <aside className="adm-sidebar">
      <div className="adm-brand">
        <img className="adm-brand-icon" src="/img/aaryan-manomilan-logo.png" alt="Manomilan" />
      </div>

      <div className="adm-menu-label">MENU</div>

      <nav className="adm-nav">
        {items.map((item) => {
          const Icon = ICONS[item.path] || LayoutDashboard;
          return (
            <Link
              key={item.path}
              to={item.path}
              title={item.label}
              className={`adm-link ${activePath === item.path ? "active" : ""}`}
              onClick={onNavigate}
            >
              <span className="adm-link-icon" style={{ "--adm-icon-color": ICON_COLORS[item.path] || "#64748b" }}>
                <Icon size={20} strokeWidth={2.2} />
              </span>
              <span className="adm-link-text">{item.label}</span>
            </Link>
          );
        })}
      </nav>

      <a
        href="/"
        className="adm-link adm-logout"
        title="Logout"
        onClick={(event) => {
          event.preventDefault();
          clearSession();
          window.location.replace("/");
        }}
      >
        <LogOut size={20} />
        <span className="adm-link-text">Logout</span>
      </a>
    </aside>
  );
}
