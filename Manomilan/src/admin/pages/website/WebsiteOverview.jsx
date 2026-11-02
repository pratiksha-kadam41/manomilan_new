import { useCallback, useEffect, useState } from "react";
import {
  CalendarPlus,
  CheckCircle2,
  ClockAlert,
  Globe2,
  RefreshCw,
  ShieldAlert,
  XCircle,
} from "lucide-react";
import { Link } from "react-router-dom";
import AdminLayout from "../../components/AdminLayout";
import { getWebsiteOverview } from "./api";
import "../../components/admin-ui.css";
import "./websiteManagement.css";

const fallback = {
  upcoming_events: {},
  downtime: {},
  maintenance: {},
  summary: {},
};

export default function WebsiteOverview() {
  const [data, setData] = useState(fallback);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await getWebsiteOverview();
      setData({ ...fallback, ...(response?.data || {}) });
    } catch (requestError) {
      setError(requestError.message || "Unable to load website management overview.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const summary = data.summary || {};

  const cards = [
    {
      title: "Upcoming Events",
      description: "Control the floating public event button, label, colours and destination.",
      icon: CalendarPlus,
      enabled: Boolean(data.upcoming_events?.enabled),
      status: data.upcoming_events?.enabled ? "Visible" : "Hidden",
      tone: data.upcoming_events?.enabled ? "live" : "off",
      to: "/admin/website/upcoming-events",
    },
    {
      title: "Downtime Notification",
      description: "Show a scheduled notice on the homepage, login page or both.",
      icon: ClockAlert,
      enabled: Boolean(data.downtime?.enabled),
      status: data.downtime?.active ? "Active now" : data.downtime?.enabled ? "Scheduled" : "Disabled",
      tone: data.downtime?.active ? "warn" : data.downtime?.enabled ? "live" : "off",
      to: "/admin/website/downtime",
    },
    {
      title: "Full Maintenance Mode",
      description: "Block the public website with a maintenance page while keeping admin access.",
      icon: ShieldAlert,
      enabled: Boolean(data.maintenance?.enabled),
      status: data.maintenance?.active ? "Maintenance active" : data.maintenance?.enabled ? "Scheduled" : "Disabled",
      tone: data.maintenance?.active ? "warn" : data.maintenance?.enabled ? "live" : "off",
      to: "/admin/website/maintenance",
    },
  ];

  return (
    <AdminLayout currentPath="/admin/website">
      <div className="adm-ui wm-page">
        <section className="wm-hero">
          <h1>Website Management</h1>
          <p>Manage public website availability, announcements and maintenance from one secure place.</p>
          <div className="wm-hero-actions">
            <button type="button" className="wm-link-btn" onClick={load} disabled={loading}>
              <RefreshCw size={15} />
              {loading ? "Refreshing..." : "Refresh overview"}
            </button>
            <Link className="wm-link-btn" to="/">
              <Globe2 size={15} />
              Open website
            </Link>
          </div>
        </section>

        {error ? <div className="wm-alert error">{error}</div> : null}

        <section className="wm-grid">
          {cards.map(({ title, description, icon: Icon, status, tone, to }) => (
            <article className="wm-card" key={title}>
              <div className="wm-card-head">
                <span className="wm-card-icon"><Icon size={20} /></span>
                <div>
                  <h2>{title}</h2>
                  <small>Public website feature</small>
                </div>
              </div>
              <p>{description}</p>
              <div className="wm-card-foot">
                <span className={`wm-pill ${tone}`}>{status}</span>
                <Link className="wm-link-btn" to={to}>Manage</Link>
              </div>
            </article>
          ))}
        </section>

        <section className="wm-layout">
          <div className="wm-panel">
            <h2>Live availability summary</h2>
            <p>Status is calculated using the Asia/Kolkata timezone and saved schedules.</p>
            <div className="wm-status-line">
              <span>Upcoming Events button</span>
              <strong>{summary.upcoming_events_enabled ? <><CheckCircle2 size={14} /> Visible</> : <><XCircle size={14} /> Hidden</>}</strong>
            </div>
            <div className="wm-status-line">
              <span>Downtime notification</span>
              <strong>{summary.downtime_active ? <><CheckCircle2 size={14} /> Active now</> : summary.downtime_enabled ? <><ClockAlert size={14} /> Scheduled</> : <><XCircle size={14} /> Disabled</>}</strong>
            </div>
            <div className="wm-status-line">
              <span>Full maintenance mode</span>
              <strong>{summary.maintenance_active ? <><ShieldAlert size={14} /> Blocking public site</> : summary.maintenance_enabled ? <><ClockAlert size={14} /> Scheduled</> : <><CheckCircle2 size={14} /> Public site available</>}</strong>
            </div>
            <div className="wm-status-line">
              <span>Timezone</span>
              <strong>{summary.timezone || "Asia/Kolkata"}</strong>
            </div>
          </div>

          <aside className="wm-side">
            <div className="wm-side-card">
              <h3>Admin access is protected</h3>
              <p>Maintenance mode never blocks the admin dashboard, admin APIs or administrator login.</p>
            </div>
            <div className="wm-side-card">
              <h3>Audit trail</h3>
              <p>Every settings change is recorded with the admin, module, old value, new value, time and IP address.</p>
            </div>
          </aside>
        </section>
      </div>
    </AdminLayout>
  );
}
