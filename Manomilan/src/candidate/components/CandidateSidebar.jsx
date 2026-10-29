import {
  Heart, LayoutDashboard, User, Search, Inbox, Send, Star, Crown, LogOut,
} from "lucide-react";
import { menuItems } from "../data/candidateData";
import { clearSession } from "../../auth";

const ICONS = {
  "/candidate": LayoutDashboard,
  "/candidate/profile": User,
  "/candidate/matches": Search,
  "/candidate/interests-received": Inbox,
  "/candidate/interests-sent": Send,
  "/candidate/shortlisted": Star,
  "/candidate/membership": Crown,
};

export default function CandidateSidebar({ currentPath }) {
  const handleLogout = () => {
    clearSession();
    window.location.assign("/login");
  };

  return (
    <aside className="cnd-sidebar">
      <div className="cnd-brand">
        <div className="cnd-brand-icon">
          <Heart size={22} fill="#fff" />
        </div>
        <div className="cnd-brand-text">
          <strong>Manomilan</strong>
          <small>Candidate Panel</small>
        </div>
      </div>

      <div className="cnd-menu-label">MENU</div>

      <nav className="cnd-nav">
        {menuItems.map((item) => {
          const Icon = ICONS[item.path] || LayoutDashboard;
          return (
            <a
              key={item.path}
              href={item.path}
              title={item.label}
              className={`cnd-link ${currentPath === item.path ? "active" : ""}`}
            >
              <Icon size={20} />
              <span className="cnd-link-text">{item.label}</span>
            </a>
          );
        })}
      </nav>

      <button className="cnd-link cnd-logout" onClick={handleLogout} title="Log Out">
        <LogOut size={20} />
        <span className="cnd-link-text">Log Out</span>
      </button>
    </aside>
  );
}