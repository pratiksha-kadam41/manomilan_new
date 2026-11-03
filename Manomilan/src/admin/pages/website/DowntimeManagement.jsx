import { useCallback, useEffect, useState } from "react";
import { ClockAlert, Save, TimerReset } from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import { useAdminPermission } from "../../../permission-context";
import ToggleField from "./ToggleField";
import { getDowntimeSettings, saveDowntimeSettings } from "./api";
import "../../components/admin-ui.css";
import "./websiteManagement.css";

const EMPTY = {
  enabled: false,
  title: "Scheduled Downtime",
  message: "",
  starts_at: "",
  ends_at: "",
  show_countdown: true,
  contact_email: "",
  contact_phone: "",
  show_contact: true,
  display_location: "both",
};

const toForm = (data = {}) => ({
  enabled: Boolean(data.enabled),
  title: data.title || EMPTY.title,
  message: data.message || "",
  starts_at: data.starts_at_input || "",
  ends_at: data.ends_at_input || "",
  show_countdown: Boolean(data.show_countdown),
  contact_email: data.contact_email || "",
  contact_phone: data.contact_phone || "",
  show_contact: Boolean(data.show_contact),
  display_location: data.display_location || "both",
});

export default function DowntimeManagement() {
  const canEditSettings = useAdminPermission("edit", "/admin/website/downtime");
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
      const response = await getDowntimeSettings();
      setForm(toForm(response?.data));
      setStatus(response?.data || {});
    } catch (requestError) {
      setError(requestError.message || "Unable to load downtime settings.");
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

  const toggle = (name) => (event) => {
    setForm((previous) => ({ ...previous, [name]: event.target.checked }));
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
    setError("");
    setNotice("");
    setSaving(true);
    try {
      const response = await saveDowntimeSettings({
        ...form,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
      });
      setForm(toForm(response?.data));
      setStatus(response?.data || {});
      setNotice(response?.message || "Downtime settings saved.");
    } catch (requestError) {
      setError(requestError.message || "Unable to save downtime settings.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <AdminLayout currentPath="/admin/website/downtime">
      <div className="adm-ui wm-page">
        <section className="wm-hero">
          <h1>Downtime Management</h1>
          <p>Display a scheduled notice below the header on the homepage, login page or both.</p>
        </section>

        {error ? <div className="wm-alert error">{error}</div> : null}
        {notice ? <div className="wm-alert success">{notice}</div> : null}

        <form className="wm-layout" onSubmit={submit}>
          <div className="wm-panel">
            <h2>Notification settings</h2>
            <p>The notice becomes visible automatically inside the start and end time. Times use Asia/Kolkata.</p>

            <ToggleField
              label="Enable downtime notification"
              description="Visitors will see the notice during the configured window."
              checked={form.enabled}
              onChange={toggle("enabled")}
              disabled={loading || saving}
            />

            <div className="wm-form-grid">
              <div className="wm-field full">
                <label htmlFor="title">Title</label>
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
                <small>End time must be after the start time.</small>
              </div>

              <div className="wm-field">
                <label htmlFor="display_location">Display location</label>
                <select id="display_location" name="display_location" value={form.display_location} onChange={setField} disabled={loading || saving}>
                  <option value="both">Homepage and Login page</option>
                  <option value="home">Homepage only</option>
                  <option value="login">Login page only</option>
                </select>
              </div>

              <div className="wm-field">
                <label htmlFor="show_countdown">Countdown</label>
                <ToggleField
                  label="Show countdown"
                  description="Display the remaining time until the end."
                  checked={form.show_countdown}
                  onChange={toggle("show_countdown")}
                  disabled={loading || saving}
                />
              </div>

              <div className="wm-field full">
                <ToggleField
                  label="Show contact details"
                  description="Let visitors contact support during the downtime."
                  checked={form.show_contact}
                  onChange={toggle("show_contact")}
                  disabled={loading || saving}
                />
              </div>

              <div className="wm-field">
                <label htmlFor="contact_email">Contact email</label>
                <input id="contact_email" name="contact_email" type="email" value={form.contact_email} onChange={setField} maxLength={150} disabled={loading || saving} />
              </div>

              <div className="wm-field">
                <label htmlFor="contact_phone">Contact phone</label>
                <input id="contact_phone" name="contact_phone" value={form.contact_phone} onChange={setField} maxLength={30} disabled={loading || saving} />
              </div>
            </div>

            <div className="wm-actions">
              <button type="button" className="ui-btn secondary" onClick={load} disabled={loading || saving}>Reset</button>
              <button type="submit" className="ui-btn" disabled={!canEditSettings || loading || saving}>
                <Save size={16} />
                {saving ? "Saving..." : "Save settings"}
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
                <span>Live now</span>
                <strong>{status.active ? "Yes" : "No"}</strong>
              </div>
              <div className="wm-status-line">
                <span>Timezone</span>
                <strong>Asia/Kolkata</strong>
              </div>
            </div>
            <div className="wm-preview">
              <span className="wm-preview-label">Preview</span>
              <span className="wm-preview-note">
                <ClockAlert size={15} /> {form.title || "Scheduled Downtime"}
                {form.show_countdown ? <><br /><TimerReset size={14} /> Countdown shown</> : null}
              </span>
            </div>
          </aside>
        </form>
      </div>
    </AdminLayout>
  );
}
