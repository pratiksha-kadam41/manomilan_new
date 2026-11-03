import { useContext, useMemo, useState } from "react";
import { CalendarPlus, ChevronDown, ClockAlert, Globe2, ShieldAlert } from "lucide-react";
import { Link } from "react-router-dom";
import { AdminPermissionContext, hasModulePermission } from "../../permission-context.jsx";
import "./websiteSidebarGroup.css";

const LINKS = [
  { label: "Website Overview", path: "/admin/website", icon: Globe2 },
  { label: "Upcoming Events", path: "/admin/website/upcoming-events", icon: CalendarPlus },
  { label: "Downtime Management", path: "/admin/website/downtime", icon: ClockAlert },
  { label: "Full Maintenance Mode", path: "/admin/website/maintenance", icon: ShieldAlert },
];

export default function WebsiteSidebarGroup({ currentPath, onNavigate }) {
  const access = useContext(AdminPermissionContext);
  const [open, setOpen] = useState(true);

  const allowed = useMemo(() => {
    if (!access?.modules) return [];
    return LINKS.filter((link) => hasModulePermission(access, "view", link.path));
  }, [access]);

  if (!allowed.length) return null;

  const active = allowed.some((link) =>
    currentPath === link.path || currentPath.startsWith(`${link.path}/`),
  );

  return (
    <div className={`wm-nav-group ${open ? "open" : ""} ${active ? "active" : ""}`}>
      <button type="button" className="wm-nav-parent" onClick={() => setOpen((value) => !value)}>
        <span className="wm-nav-parent-icon"><Globe2 size={18} /></span>
        <span className="adm-link-text">Website Management</span>
        <ChevronDown size={15} className="wm-nav-chevron" />
      </button>
      {open ? (
        <div className="wm-nav-children">
          {allowed.map(({ label, path, icon: Icon }) => (
            <Link
              key={path}
              to={path}
              className={`wm-nav-child ${currentPath === path ? "active" : ""}`}
              onClick={onNavigate}
            >
              <Icon size={16} />
              <span className="adm-link-text">{label}</span>
            </Link>
          ))}
        </div>
      ) : null}
    </div>
  );
}
