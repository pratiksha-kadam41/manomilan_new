import { useEffect, useRef, useState } from "react";
import {
  Bell, Heart, Home, KeyRound, LogOut, Menu,
  User, Users, Compass,
} from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import CandidateSidebar from "./CandidateSidebar";
import { useProfileCompletion } from "../profile-completion-context";
import {
  clearSession, getCachedHomeData, getSession,
} from "../../auth";
import { apiMediaUrl } from "../../api";
import { getUnreadNotificationCount } from "../api";
import { initials } from "../utils";
import "../candidate.css";

const TABS = [
  ["/candidate", "Home", Home],
  ["/candidate/matches", "Discover", Compass],
  ["/candidate/interests-received", "Interests", Heart],
  ["/candidate/profile", "Profile", User],
];

export default function CandidateLayout({ currentPath, candidateInfo, children }) {
  const navigate = useNavigate();
  const [collapsed, setCollapsed] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const profileCompletion = useProfileCompletion();
  const cachedHome = getCachedHomeData();
  const menuRef = useRef(null);
  const session = getSession();
  const sessionUser = session?.user;
  const sessionProfile = sessionUser?.profile || {};

  const candidateName =
    (candidateInfo?.name && !["Candidate", "Your profile"].includes(candidateInfo.name)
      ? candidateInfo.name
      : "")
    || sessionProfile.full_name
    || sessionProfile.fullName
    || cachedHome?.profile?.full_name
    || sessionUser?.name
    || sessionUser?.username
    || "Candidate";
  const candidatePhoto =
    apiMediaUrl(
      candidateInfo?.photo
      || cachedHome?.profile?.photo_url
      || sessionProfile.photo_url
      || sessionProfile.profilePhoto,
    );

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
    navigate(path);
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
        <header className="mm-header">
          <div className="mm-header-content">
            <button
              type="button"
              className="mm-icon-btn cnd-collapse-btn"
              onClick={() => setCollapsed((value) => !value)}
              aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
            >
              <Menu size={24} />
            </button>
            <Link to="/candidate" aria-label="Candidate dashboard" className="mm-logo-link">
              <img src="/website/Mano_Milan_Final_Website.png" alt="Aaryans Manomilan" className="mm-logo" />
            </Link>
            <div className="mm-header-actions">
              <button
                type="button"
                className="mm-icon-btn"
                aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ""}`}
                onClick={() => go("/candidate/notifications")}
              >
                <Bell size={24} />
                {unreadCount > 0 ? (
                  <span className="mm-notification-badge">{unreadCount > 99 ? "99+" : unreadCount}</span>
                ) : null}
              </button>

              <div className="cnd-user-menu" ref={menuRef}>
                <button
                  type="button"
                  className="mm-avatar-small"
                  onClick={() => setMenuOpen((value) => !value)}
                  aria-expanded={menuOpen}
                  aria-haspopup="menu"
                >
                  {candidatePhoto ? (
                    <img
                      src={candidatePhoto}
                      alt=""
                      onError={(event) => event.currentTarget.remove()}
                    />
                  ) : (
                    <span style={{display:'flex',alignItems:'center',justifyContent:'center',width:'100%',height:'100%',background:'var(--mm-border)',color:'var(--mm-primary)',fontSize:'12px',fontWeight:'600'}}>{initials(candidateName)}</span>
                  )}
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
          </div>
        </header>

          {profileCompletion.status === "required" && currentPath !== "/candidate/profile" ? (
              <section className="cnd-profile-completion-banner" role="status">
                <div>
                  <strong>Complete your profile to unlock all features</strong>
                  <span>Your profile is {profileCompletion.completion}% complete.</span>
                </div>
                <Link to="/candidate/profile">
                  {profileCompletion.hasProfile ? "Complete profile" : "Create profile"}
                </Link>
              </section>
            ) : null}

          <main className="mm-main cnd-content">{children}</main>
      </div>

      <nav className="mm-bottom-nav" aria-label="Candidate mobile navigation">
        <div className="mm-bottom-nav-content">
          {TABS.map(([path, label, Icon]) => {
            const active = currentPath === path
              || (path === "/candidate/interests-received"
                && (currentPath === "/candidate/interests-sent" || currentPath?.includes("interests")));
            return (
              <Link
                key={path}
                to={path}
                className={`mm-nav-item ${active ? "active" : ""}`}
                aria-current={active ? "page" : undefined}
              >
                <Icon size={22} strokeWidth={active ? 2.5 : 1.8} />
                <span>{label}</span>
                {label === "Interests" && unreadCount > 0 ? (
                  <span className="mm-nav-badge">{unreadCount > 9 ? "9+" : unreadCount}</span>
                ) : null}
              </Link>
            );
          })}
        </div>
      </nav>
    </div>
  );
}
