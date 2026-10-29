import { useEffect, useRef, useState } from "react";
import { Menu, ChevronDown, KeyRound, LogOut } from "lucide-react";
import { clearSession } from "../../auth";
import Sidebar from "./Sidebar";
import "../admin.css";

export default function AdminLayout({ currentPath, children }) {
  const [collapsed, setCollapsed] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);

  // Close the dropdown when clicking anywhere outside it
  useEffect(() => {
    const handleClick = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setMenuOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  const handleLogout = () => {
  setMenuOpen(false);
  clearSession();                  // removes the session and cancels it on the server
  window.location.replace("/");   // replace, so Back can't return to the admin page
};
  const handleChangePassword = () => {
    setMenuOpen(false);
    window.location.assign("/admin/change-password");
  };

  return (
    <div className={`adm-wrapper ${collapsed ? "collapsed" : ""}`}>
      <Sidebar currentPath={currentPath} />

      <div className="adm-main">
        <header className="adm-topbar">
          <button
            className="adm-toggle"
            onClick={() => setCollapsed(!collapsed)}
            aria-label="Toggle sidebar"
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

        <main className="adm-content">{children}</main>
      </div>
    </div>
  );
}