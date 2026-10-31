import { useEffect, useRef, useState } from "react";
import { Menu, ChevronDown, KeyRound, LogOut } from "lucide-react";
import { clearSession } from "../../auth";
import Sidebar from "./Sidebar";
import "../admin.css";

export default function AdminLayout({ currentPath, contentClassName = "", children }) {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);

  useEffect(() => {
    const handleClick = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setMenuOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  useEffect(() => {
    if (!mobileOpen) return undefined;
    const handleKeyDown = (event) => {
      if (event.key === "Escape") setMobileOpen(false);
    };
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [mobileOpen]);

  const handleLogout = () => {
    setMenuOpen(false);
    clearSession();
    window.location.replace("/");
  };

  const handleChangePassword = () => {
    setMenuOpen(false);
    window.location.assign("/admin/change-password");
  };

  const toggleNavigation = () => {
    if (window.matchMedia("(max-width: 768px)").matches) {
      setMobileOpen((open) => !open);
      return;
    }
    setCollapsed((value) => !value);
  };

  return (
    <div className={`adm-wrapper ${collapsed ? "collapsed" : ""} ${mobileOpen ? "mobile-open" : ""}`}>
      {mobileOpen ? (
        <button
          type="button"
          className="adm-sidebar-overlay"
          aria-label="Close navigation menu"
          onClick={() => setMobileOpen(false)}
        />
      ) : null}
      <Sidebar
        currentPath={currentPath}
        onNavigate={() => setMobileOpen(false)}
      />

      <div className="adm-main">
        <header className="adm-topbar">
          <button
            className="adm-toggle"
            type="button"
            onClick={toggleNavigation}
            aria-label="Toggle navigation menu"
            aria-expanded={
              window.matchMedia("(max-width: 768px)").matches
                ? mobileOpen
                : !collapsed
            }
          >
            <Menu size={22} />
          </button>

          <div className="adm-profile" ref={menuRef}>
            <button
              className={`adm-user ${menuOpen ? "open" : ""}`}
              onClick={() => setMenuOpen(!menuOpen)}
            >
              <div className="adm-avatar">S</div>
              <div className="adm-user-text">
                <strong>Super admin</strong>
                <small>super</small>
              </div>
              <ChevronDown size={18} className="adm-chevron" />
            </button>

            {menuOpen && (
              <div className="adm-dropdown">
                <button onClick={handleChangePassword}>
                  <KeyRound size={17} /> Change Password
                </button>
                <button className="danger" onClick={handleLogout}>
                  <LogOut size={17} /> Log Out
                </button>
              </div>
            )}
          </div>
        </header>

        <main className={`adm-content ${contentClassName}`.trim()}>{children}</main>
      </div>
    </div>
  );
}