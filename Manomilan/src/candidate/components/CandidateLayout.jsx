import { useEffect, useRef, useState } from "react";
import { Menu, ChevronDown, User, KeyRound, LogOut } from "lucide-react";
import CandidateSidebar from "./CandidateSidebar";
import { candidate } from "../data/candidateData";
import { clearSession } from "../../auth";
import "../candidate.css";

export default function CandidateLayout({ currentPath, children }) {
  const [collapsed, setCollapsed] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);

  // Close the dropdown when clicking outside it
  useEffect(() => {
    const handleClick = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false);
    };
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
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
    <div className={`cnd-wrapper ${collapsed ? "collapsed" : ""}`}>
      <CandidateSidebar currentPath={currentPath} />

      <div className="cnd-main">
        <header className="cnd-topbar">
          <button
            className="cnd-toggle"
            onClick={() => setCollapsed(!collapsed)}
            aria-label="Toggle sidebar"
          >
            <Menu size={22} />
          </button>

          <div className="cnd-profile" ref={menuRef}>
            <button
              className={`cnd-user ${menuOpen ? "open" : ""}`}
              onClick={() => setMenuOpen(!menuOpen)}
            >
              <div className="cnd-avatar">{candidate.name.charAt(0)}</div>
              <div className="cnd-user-text">
                <strong>{candidate.name}</strong>
                <small>{candidate.id}</small>
              </div>
              <ChevronDown size={18} className="cnd-chevron" />
            </button>

            {menuOpen && (
              <div className="cnd-dropdown">
                <button onClick={() => go("/candidate/profile")}>
                  <User size={17} /> My Profile
                </button>
                <button onClick={() => go("/candidate/change-password")}>
                  <KeyRound size={17} /> Change Password
                </button>
                <button className="danger" onClick={handleLogout}>
                  <LogOut size={17} /> Log Out
                </button>
              </div>
            )}
          </div>
        </header>

        <main className="cnd-content">{children}</main>
      </div>
    </div>
  );
}