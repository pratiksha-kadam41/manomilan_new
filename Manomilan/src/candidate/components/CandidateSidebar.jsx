import {
  Bell, Heart, Home, Inbox, KeyRound, LogOut, Send, User, Users,
} from "lucide-react";
import { accountItems, menuItems } from "../data/candidateData";

const ICONS = {
  home: Home,
  user: User,
  search: Users,
  inbox: Inbox,
  send: Send,
  bell: Bell,
  key: KeyRound,
};

export default function CandidateSidebar({ currentPath, unreadCount = 0, onLogout }) {
  const renderItem = (item) => {
    const Icon = ICONS[item.icon] || Heart;
    const active = currentPath === item.path;
    const showBadge = item.path === "/candidate/notifications" && unreadCount > 0;

    return (
      <a
        key={item.path}
        href={item.path}
        title={item.label}
        className={`cnd-nav-link ${active ? "active" : ""}`}
        aria-current={active ? "page" : undefined}
      >
        <span className="cnd-nav-icon"><Icon size={19} /></span>
        <span className="cnd-nav-text">{item.label}</span>
        {showBadge ? (
          <span className="cnd-nav-badge">{unreadCount > 99 ? "99+" : unreadCount}</span>
        ) : null}
      </a>
    );
  };

  return (
    <aside className="cnd-sidebar">
      <a className="cnd-sidebar-brand" href="/candidate" aria-label="Aaryans Manomilan candidate portal">
        <img src="/img/aaryan-manomilan-logo.png" alt="Aaryans Manomilan" />
      </a>

      <div className="cnd-sidebar-scroll">
        <span className="cnd-menu-label">Main menu</span>
        <nav className="cnd-nav" aria-label="Candidate navigation">
          {menuItems.map(renderItem)}
        </nav>

        <span className="cnd-menu-label">Account</span>
        <nav className="cnd-nav" aria-label="Account navigation">
          {accountItems.map(renderItem)}
        </nav>
      </div>

      <div className="cnd-sidebar-foot">
        <button type="button" className="cnd-nav-link cnd-logout" onClick={onLogout}>
          <span className="cnd-nav-icon"><LogOut size={19} /></span>
          <span className="cnd-nav-text">Log Out</span>
        </button>
        <p>Manomilan candidate portal</p>
      </div>
    </aside>
  );
}
