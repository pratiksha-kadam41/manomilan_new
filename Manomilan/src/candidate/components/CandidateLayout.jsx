import { useEffect, useRef, useState } from "react";
import {
  Bell, ChevronDown, Heart, Home, KeyRound, LogOut, Menu, Search,
  User, Users,
} from "lucide-react";
import CandidateSidebar from "./CandidateSidebar";
import { clearSession, getSession } from "../../auth";
import { apiUrl } from "../../api";
import { getUnreadNotificationCount } from "../api";
import { initials, text } from "../utils";
import "../candidate.css";

const MEDIA_BASE = apiUrl.replace(/\/api\/?$/i, "");

const mediaUrl = (value) => {
  const path = text(value);
  if (!path) return "";
  if (/^https?:\/\//i.test(path)) return path;
  if (path.startsWith("/")) return `${MEDIA_BASE}${path}`;
  return `${MEDIA_BASE}/${path}`;
};

const TABS = [
  ["/candidate", "Home", Home],
  ["/candidate/matches", "Matches", Search],
  ["/candidate/interests-received", "Interests", Heart],
  ["/candidate/profile", "Profile", User],
];

export default function CandidateLayout({ currentPath, candidateInfo, children }) {
  const [collapsed, setCollapsed] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const menuRef = useRef(null);
  const session = getSession();
  const sessionUser = session?.user;
  const sessionProfile = sessionUser?.profile || {};

  const candidateName =
    candidateInfo?.name
    || sessionProfile.full_name
    || sessionProfile.fullName
    || sessionUser?.name
    || sessionUser?.username
    || "Candidate";
  const candidateId =
    candidateInfo?.id
    || sessionProfile.registration_number
    || sessionProfile.profile_id
    || sessionUser?.username
    || "Member";
  const candidatePhoto =
    candidateInfo?.photo
    || sessionProfile.photo_url
    || mediaUrl(sessionProfile.profilePhoto);

  useEffect(() => {
    document.body.classList.add("candidate-mode");
    return () => document.body.classList.remove("candidate-mode");
  }, []);

  useEffect(() => {
    let active = true;
    const loadCount = async () => {
      try {
        const result = await getUnreadNotificationCount();
        if (active) setUnreadCount(Number(result?.unread_count) || 0);
      } catch {
        /* The bell is non-critical; ignore transient failures. */
      }
    };

    loadCount();
    const interval = window.setInterval(loadCount, 60000);
    window.addEventListener("candidate:notifications-updated", loadCount);

    return () => {
      active = false;
      window.clearInterval(interval);
      window.removeEventListener("candidate:notifications-updated", loadCount);
    };
  }, []);

  useEffect(() => {
    const onClick = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) setMenuOpen(false);
    };
    const onKey = (event) => {
      if (event.key === "Escape") setMenuOpen(false);
    };
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onClick);
      document.removeEventListener("keydown", onKey);
    };
  }, []);

  const go = (path) => {
    setMenuOpen(false);
    window.location.assign(path);
  };

  const handleLogout = () => {
    clearSession();
    window.location.assign("/login");
  };

  return (
    <div className={`cnd-shell ${collapsed ? "is-collapsed" : ""}`}>
      <CandidateSidebar
        currentPath={currentPath}
        unreadCount={unreadCount}
        onLogout={handleLogout}
      />

      <div className="cnd-main">
        <header className="cnd-topbar">
          <div className="cnd-topbar-left">
            <button
              type="button"
              className="cnd-icon-btn cnd-collapse-btn"
              onClick={() => setCollapsed((value) => !value)}
              aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
            >
              <Menu size={21} />
            </button>
            <a href="/candidate" className="cnd-mobile-brand" aria-label="Candidate dashboard">
              <img src="/img/aaryan-manomilan-logo.png" alt="Aaryans Manomilan" />
            </a>
            <div className="cnd-topbar-title">
              <span>Candidate Portal</span>
              <small>Find your life partner with confidence</small>
            </div>
          </div>

          <div className="cnd-topbar-right">
            <a href="/candidate/matches" className="cnd-topbar-search" title="Find matches">
              <Search size={17} />
              <span>Find matches</span>
            </a>

            <button
              type="button"
              className="cnd-icon-btn cnd-bell"
              aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ""}`}
              onClick={() => go("/candidate/notifications")}
            >
              <Bell size={20} />
              {unreadCount > 0 ? (
                <span className="cnd-bell-count">{unreadCount > 99 ? "99+" : unreadCount}</span>
              ) : null}
            </button>

            <div className="cnd-user-menu" ref={menuRef}>
              <button
                type="button"
                className={`cnd-user-btn ${menuOpen ? "open" : ""}`}
                onClick={() => setMenuOpen((value) => !value)}
                aria-expanded={menuOpen}
                aria-haspopup="menu"
              >
                <span className="cnd-user-avatar">
                  {candidatePhoto ? <img src={candidatePhoto} alt="" /> : initials(candidateName)}
                </span>
                <span className="cnd-user-meta">
                  <strong>{candidateName}</strong>
                  <small>{candidateId}</small>
                </span>
                <ChevronDown size={16} className="cnd-user-chevron" />
              </button>

              {menuOpen ? (
                <div className="cnd-dropdown" role="menu">
                  <button type="button" role="menuitem" onClick={() => go("/candidate/profile")}>
                    <User size={17} /> My Profile
                  </button>
                  <button type="button" role="menuitem" onClick={() => go("/candidate/matches")}>
                    <Users size={17} /> Find Matches
                  </button>
                  <button type="button" role="menuitem" onClick={() => go("/candidate/notifications")}>
                    <Bell size={17} /> Notifications
                    {unreadCount > 0 ? <span className="cnd-dropdown-count">{unreadCount}</span> : null}
                  </button>
                  <button type="button" role="menuitem" onClick={() => go("/candidate/change-password")}>
                    <KeyRound size={17} /> Change Password
                  </button>
                  <span className="cnd-dropdown-divider" />
                  <button type="button" role="menuitem" className="danger" onClick={handleLogout}>
                    <LogOut size={17} /> Log Out
                  </button>
                </div>
              ) : null}
            </div>
          </div>
        </header>

        <main className="cnd-content">{children}</main>
      </div>

      <nav className="cnd-bottom-nav" aria-label="Candidate mobile navigation">
        {TABS.map(([path, label, Icon]) => {
          const active = currentPath === path
            || (path === "/candidate/interests-received"
              && currentPath === "/candidate/interests-sent");
          return (
            <a
              key={path}
              href={path}
              className={`cnd-bottom-tab ${active ? "active" : ""}`}
              aria-current={active ? "page" : undefined}
            >
              <Icon size={21} strokeWidth={active ? 2.6 : 1.9} />
              <span>{label}</span>
            </a>
          );
        })}
      </nav>
    </div>
  );
}
