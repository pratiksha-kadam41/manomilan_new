import { useCallback, useEffect, useState } from "react";
import {
  Bell,
  Calendar,
  Gift,
  Megaphone,
  Save,
  Sparkles,
  Star,
} from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import { useAdminPermission } from "../../../permission-context";
import ToggleField from "./ToggleField";
import { getUpcomingEventsSettings, saveUpcomingEventsSettings } from "./api";
import "../../components/admin-ui.css";
import "./websiteManagement.css";

const ICONS = [
  { value: "calendar", label: "Calendar", Icon: Calendar },
  { value: "sparkles", label: "Sparkles", Icon: Sparkles },
  { value: "megaphone", label: "Megaphone", Icon: Megaphone },
  { value: "bell", label: "Bell", Icon: Bell },
  { value: "gift", label: "Gift", Icon: Gift },
  { value: "star", label: "Star", Icon: Star },
];

const EMPTY = {
  enabled: true,
  label: "Upcoming Events",
  icon: "calendar",
  background_color: "#c4144f",
  text_color: "#ffffff",
  position: "bottom-right",
  url: "",
  open_new_tab: true,
};

export default function UpcomingEventsSettings() {
  const canEditSettings = useAdminPermission("edit", "/admin/website/upcoming-events");
  const [form, setForm] = useState(EMPTY);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await getUpcomingEventsSettings();
      setForm({ ...EMPTY, ...(response?.data || {}), url: response?.data?.url || "" });
    } catch (requestError) {
      setError(requestError.message || "Unable to load Upcoming Events settings.");
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
      const response = await saveUpcomingEventsSettings(form);
      setForm({ ...EMPTY, ...(response?.data || {}), url: response?.data?.url || "" });
      setNotice(response?.message || "Upcoming Events settings saved.");
    } catch (requestError) {
      setError(requestError.message || "Unable to save Upcoming Events settings.");
    } finally {
      setSaving(false);
    }
  };

  const selectedIcon = ICONS.find((item) => item.value === form.icon) || ICONS[0];
  const PreviewIcon = selectedIcon.Icon;

  return (
    <AdminLayout currentPath="/admin/website/upcoming-events">
      <div className="adm-ui wm-page">
        <section className="wm-hero">
          <h1>Upcoming Events Settings</h1>
          <p>Control the floating public button shown on the website homepage.</p>
        </section>

        {error ? <div className="wm-alert error">{error}</div> : null}
        {notice ? <div className="wm-alert success">{notice}</div> : null}

        <form className="wm-layout" onSubmit={submit}>
          <div className="wm-panel">
            <h2>Button settings</h2>
            <p>Changes apply to the public homepage after saving.</p>

            <ToggleField
              label="Show Upcoming Events button"
              description="Turn the floating button on or off for all public visitors."
              checked={form.enabled}
              onChange={(event) => setField({ target: { name: "enabled", type: "checkbox", checked: event.target.checked } })}
              disabled={loading || saving}
            />

            <div className="wm-form-grid">
              <div className="wm-field full">
                <label htmlFor="label">Button text</label>
                <input id="label" name="label" value={form.label} onChange={setField} maxLength={80} disabled={loading || saving} required />
              </div>

              <div className="wm-field">
                <label htmlFor="icon">Icon</label>
                <select id="icon" name="icon" value={form.icon} onChange={setField} disabled={loading || saving}>
                  {ICONS.map(({ value, label }) => <option key={value} value={value}>{label}</option>)}
                </select>
              </div>

              <div className="wm-field">
                <label htmlFor="position">Position</label>
                <select id="position" name="position" value={form.position} onChange={setField} disabled={loading || saving}>
                  <option value="bottom-right">Bottom right</option>
                  <option value="bottom-left">Bottom left</option>
                </select>
              </div>

              <div className="wm-field">
                <label htmlFor="background_color">Background colour</label>
                <div className="wm-color-row">
                  <input id="background_color" name="background_color" type="color" value={form.background_color} onChange={setField} disabled={loading || saving} />
                  <input name="background_color" value={form.background_color} onChange={setField} maxLength={7} disabled={loading || saving} aria-label="Background colour hex" />
                </div>
              </div>

              <div className="wm-field">
                <label htmlFor="text_color">Text colour</label>
                <div className="wm-color-row">
                  <input id="text_color" name="text_color" type="color" value={form.text_color} onChange={setField} disabled={loading || saving} />
                  <input name="text_color" value={form.text_color} onChange={setField} maxLength={7} disabled={loading || saving} aria-label="Text colour hex" />
                </div>
              </div>

              <div className="wm-field full">
                <label htmlFor="url">Destination URL</label>
                <input id="url" name="url" value={form.url} onChange={setField} placeholder="https://example.com/events or /melava-registration" disabled={loading || saving} />
                <small>Use an https:// link or a website path starting with /. Leave blank to show a non-clickable button.</small>
              </div>

              <div className="wm-field full">
                <ToggleField
                  label="Open in a new tab"
                  description="Recommended for external event pages."
                  checked={form.open_new_tab}
                  onChange={(event) => setField({ target: { name: "open_new_tab", type: "checkbox", checked: event.target.checked } })}
                  disabled={loading || saving}
                />
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
            <div className="wm-preview">
              <span className="wm-preview-label">Live preview</span>
              <span
                className="wm-preview-button"
                style={{ background: form.background_color, color: form.text_color }}
              >
                <PreviewIcon size={17} />
                {form.label || "Upcoming Events"}
              </span>
              <span className="wm-preview-note">
                Position: {form.position === "bottom-left" ? "Bottom left" : "Bottom right"} · {form.open_new_tab ? "Opens in new tab" : "Opens in same tab"}
              </span>
            </div>
            <div className="wm-side-card">
              <h3>Security</h3>
              <ul>
                <li>Only http(s) links or website paths are accepted.</li>
                <li>All changes are written to the audit log.</li>
                <li>Public visitors receive a cached settings payload.</li>
              </ul>
            </div>
          </aside>
        </form>
      </div>
    </AdminLayout>
  );
}
