import { useCallback, useEffect, useState } from "react";
import { AlertTriangle, Save, ShieldAlert } from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import { useAdminPermission } from "../../../permission-context";
import ToggleField from "./ToggleField";
import { getMaintenanceSettings, saveMaintenanceSettings } from "./api";
import "../../components/admin-ui.css";
import "./websiteManagement.css";

const EMPTY = {
  enabled: false,
  title: "We will be back soon",
  message: "",
  starts_at: "",
  ends_at: "",
};

const toForm = (data = {}) => ({
  enabled: Boolean(data.enabled),
  title: data.title || EMPTY.title,
  message: data.message || "",
  starts_at: data.starts_at_input || "",
  ends_at: data.ends_at_input || "",
});

export default function FullMaintenanceMode() {
  const canEditSettings = useAdminPermission("edit", "/admin/website/maintenance");
  const [form, setForm] = useState(EMPTY);
  const [status, setStatus] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await getMaintenanceSettings();
      setForm(toForm(response?.data));
      setStatus(response?.data || {});
    } catch (requestError) {
      setError(requestError.message || "Unable to load maintenance settings.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const setField = (event) => {
    const { name, value, checked, type } = event.target;
    setForm((previous) => ({ ...previous, [name]: type === "checkbox" ? checked : value }));
    setError("");
    setNotice("");
  };

  const toggle = (event) => {
    setForm((previous) => ({ ...previous, enabled: event.target.checked }));
    setError("");
    setNotice("");
  };

  const submit = async (event) => {
    event.preventDefault();
    if (saving) return;
    if (!canEditSettings) {
      setError("You do not have permission to change website settings.");
      return;
    }

    if (form.enabled && !status.active) {
      const confirmed = window.confirm(
        "Enable full maintenance mode? Public website visitors will see the maintenance page until the schedule ends or you disable it. Admin access will remain available.",
      );
      if (!confirmed) return;
    }

    setError("");
    setNotice("");
    setSaving(true);
    try {
      const response = await saveMaintenanceSettings({
        ...form,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
      });
      setForm(toForm(response?.data));
      setStatus(response?.data || {});
      setNotice(response?.message || "Maintenance settings saved.");
    } catch (requestError) {
      setError(requestError.message || "Unable to save maintenance settings.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <AdminLayout currentPath="/admin/website/maintenance">
      <div className="adm-ui wm-page">
        <section className="wm-hero">
          <h1>Full Maintenance Mode</h1>
          <p>Block public website access with a dedicated maintenance page. Admin dashboard and admin APIs remain available.</p>
        </section>

        {error ? <div className="wm-alert error">{error}</div> : null}
        {notice ? <div className="wm-alert success">{notice}</div> : null}

        <form className="wm-layout" onSubmit={submit}>
          <div className="wm-panel wm-danger-panel">
            <h2>Maintenance configuration</h2>
            <p>Public visitors are blocked by backend middleware, not only by the frontend. Times use Asia/Kolkata.</p>

            <ToggleField
              label="Enable full maintenance mode"
              description="Public pages and public APIs will return a maintenance response during the active window."
              checked={form.enabled}
              onChange={toggle}
              disabled={loading || saving}
            />

            {form.enabled ? (
              <div className="wm-alert warn">
                <AlertTriangle size={17} />
                <span>
                  {status.active
                    ? "Maintenance mode is active right now. Public visitors are being blocked."
                    : "Maintenance mode is enabled. It will activate automatically when the schedule starts, or immediately if no start time is set."}
                </span>
              </div>
            ) : null}

            <div className="wm-form-grid">
              <div className="wm-field full">
                <label htmlFor="title">Maintenance title</label>
                <input id="title" name="title" value={form.title} onChange={setField} maxLength={150} disabled={loading || saving} required />
              </div>

              <div className="wm-field full">
                <label htmlFor="message">Message</label>
                <textarea id="message" name="message" value={form.message} onChange={setField} maxLength={2000} disabled={loading || saving} />
              </div>

              <div className="wm-field">
                <label htmlFor="starts_at">Start date and time</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value={form.starts_at} onChange={setField} disabled={loading || saving} />
              </div>

              <div className="wm-field">
                <label htmlFor="ends_at">End date and time</label>
                <input id="ends_at" name="ends_at" type="datetime-local" value={form.ends_at} onChange={setField} disabled={loading || saving} />
                <small>Leave blank for an open-ended maintenance window.</small>
              </div>
            </div>

            <div className="wm-actions">
              <button type="button" className="ui-btn secondary" onClick={load} disabled={loading || saving}>Reset</button>
              <button type="submit" className={`ui-btn ${form.enabled ? "danger" : ""}`} disabled={!canEditSettings || loading || saving}>
                <Save size={16} />
                {saving ? "Saving..." : "Save maintenance settings"}
              </button>
            </div>
          </div>

          <aside className="wm-side">
            <div className="wm-side-card">
              <h3>Current status</h3>
              <div className="wm-status-line">
                <span>Configuration</span>
                <strong>{status.enabled ? "Enabled" : "Disabled"}</strong>
              </div>
              <div className="wm-status-line">
                <span>Public site</span>
                <strong>{status.active ? "Blocked" : "Available"}</strong>
              </div>
              <div className="wm-status-line">
                <span>Admin access</span>
                <strong>Always available</strong>
              </div>
            </div>
            <div className="wm-side-card">
              <h3>Protected routes</h3>
              <p><ShieldAlert size={14} /> Admin dashboard, admin APIs, candidate portal and administrator login stay accessible.</p>
            </div>
          </aside>
        </form>
      </div>
    </AdminLayout>
  );
}
