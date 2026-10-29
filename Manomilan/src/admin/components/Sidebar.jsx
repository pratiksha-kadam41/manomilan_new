import {
  Heart, LayoutDashboard, Users, Globe, BadgeCheck, ShieldAlert,
  CalendarHeart, GraduationCap, UserPlus, KeyRound, FileText, LogOut,
  CalendarPlus,
} from "lucide-react";

import { clearSession } from "../../auth";
import { menuItems } from "../data/adminData";

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
};

export default function Sidebar({ currentPath }) {
  return (
    <aside className="adm-sidebar">
      <div className="adm-brand">
        <div className="adm-brand-icon">
          <Heart size={22} fill="#fff" />
        </div>
        <div className="adm-brand-text">
          <strong>Manomilan</strong>
          <small>Admin Panel</small>
        </div>
      </div>

      <div className="adm-menu-label">MENU</div>

      <nav className="adm-nav">
        {menuItems.map((item) => {
          const Icon = ICONS[item.path] || LayoutDashboard;
          return (
            <a
              key={item.path}
              href={`#${item.path}`}
              title={item.label}
              className={`adm-link ${currentPath === item.path ? "active" : ""}`}
            >
              <Icon size={20} />
              <span className="adm-link-text">{item.label}</span>
            </a>
          );
        })}
      </nav>

      <a
  href="#/"
  className="adm-link adm-logout"
  title="Logout"
  onClick={(e) => {
    e.preventDefault();
    clearSession();
    window.location.replace("#/");
  }}
>
  <LogOut size={20} />
  <span className="adm-link-text">Logout</span>
</a>
    </aside>
  );
}