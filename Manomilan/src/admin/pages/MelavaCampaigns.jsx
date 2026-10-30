import { useCallback, useEffect, useState } from "react";
import { CalendarPlus, ImagePlus, Pencil, RefreshCw, Trash2, ToggleLeft, ToggleRight } from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { request } from "../../api";
import "./melavaCampaigns.css";

const EMPTY_FORM = {
  melava_code: "",
  title: "",
  description: "",
  start_date: "",
  end_date: "",
  currency: "INR",
  registration_fee: "",
  booklet_available: false,
  booklet_fee: "",
  qr_image: null,
};

const amount = (value, currency) =>
  new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: currency || "INR",
    maximumFractionDigits: 2,
  }).format(Number(value || 0));

export default function MelavaCampaigns() {
  const [campaigns, setCampaigns] = useState([]);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [editingId, setEditingId] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await request("/melava-campaigns");
      setCampaigns(Array.isArray(response?.data) ? response.data : []);
    } catch (requestError) {
      setError(requestError.message || "Unable to load Melava events.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const setField = (event) => {
    const { name, value, checked, files } = event.target;
    setForm((previous) => ({
      ...previous,
      [name]: name === "booklet_available"
        ? checked
        : name === "qr_image"
          ? files?.[0] || null
          : name === "melava_code"
            ? value.toUpperCase()
            : value,
    }));
    setError("");
    setNotice("");
  };

  const createCampaign = async (event) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    setError("");
    setNotice("");

    if (!form.qr_image) {
      setError("Upload a QR image for this Melava event.");
      return;
    }

    setSaving(true);
    const payload = new FormData();
    payload.append("melava_code", form.melava_code.trim().toUpperCase());
    payload.append("title", form.title.trim());
    payload.append("description", form.description.trim());
    payload.append("start_date", form.start_date);
    payload.append("end_date", form.end_date);
    payload.append("currency", form.currency);
    payload.append("registration_fee", form.registration_fee);
    payload.append("booklet_available", form.booklet_available ? "1" : "0");
    payload.append("booklet_fee", form.booklet_available ? form.booklet_fee : "0");
    payload.append("qr_image", form.qr_image);

    try {
      await request("/melava-campaigns", { method: "POST", body: payload });
      setForm({ ...EMPTY_FORM });
      formElement.reset();
      setNotice("Melava event created. It is now available on the registration form.");
      await load();
    } catch (requestError) {
      setError(requestError.message || "Unable to create the Melava event.");
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (campaign) => {
    setEditingId(campaign.id);
    setForm({
      melava_code: campaign.melava_code || "",
      title: campaign.title || "",
      description: campaign.description || "",
      start_date: campaign.start_date || "",
      end_date: campaign.end_date || "",
      currency: campaign.currency || "INR",
      registration_fee: campaign.registration_fee ?? "",
      booklet_available: Boolean(campaign.booklet_available),
      booklet_fee: campaign.booklet_fee ?? "",
      qr_image: null,
    });
    setError("");
    setNotice("");
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const cancelEdit = () => {
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
  };

  const saveCampaign = async (event) => {
    event.preventDefault();
    if (!editingId) return;
    setSaving(true);
    setError("");
    setNotice("");
    const payload = new FormData();
    payload.append("_method", "PUT");
    payload.append("melava_code", form.melava_code.trim().toUpperCase());
    payload.append("title", form.title.trim());
    payload.append("description", form.description.trim());
    payload.append("start_date", form.start_date);
    payload.append("end_date", form.end_date);
    payload.append("currency", form.currency);
    payload.append("registration_fee", form.registration_fee);
    payload.append("booklet_available", form.booklet_available ? "1" : "0");
    payload.append("booklet_fee", form.booklet_available ? form.booklet_fee : "0");
    if (form.qr_image) payload.append("qr_image", form.qr_image);
    try {
      await request(`/melava-campaigns/${editingId}`, { method: "POST", body: payload });
      cancelEdit();
      setNotice("Melava event updated.");
      await load();
    } catch (requestError) {
      setError(requestError.message || "Unable to update the Melava event.");
    } finally {
      setSaving(false);
    }
  };

  const deleteCampaign = async (campaign) => {
    if (busyId) return;
    if (!window.confirm(`Delete Melava ${campaign.melava_code || ""} (${campaign.title})?`)) return;
    setBusyId(campaign.id);
    setError("");
    setNotice("");
    try {
      await request(`/melava-campaigns/${campaign.id}`, { method: "DELETE" });
      if (editingId === campaign.id) cancelEdit();
      setNotice("Melava event deleted.");
      await load();
    } catch (requestError) {
      setError(requestError.message || "Unable to delete the Melava event.");
    } finally {
      setBusyId(null);
    }
  };

  const changeStatus = async (campaign) => {
    if (busyId) return;
    setBusyId(campaign.id);
    setError("");
    setNotice("");
    try {
      const response = await request(`/melava-campaigns/${campaign.id}/status`, {
        method: "PUT",
        body: { status: campaign.status === "active" ? "inactive" : "active" },
      });
      setCampaigns((previous) =>
        previous.map((item) => item.id === campaign.id ? response.data : item),
      );
      setNotice(
        response.data.status === "active"
          ? "Event opened for registration."
          : "Event closed. Existing registrations remain available for payment review.",
      );
    } catch (requestError) {
      setError(requestError.message || "Unable to update event status.");
    } finally {
      setBusyId(null);
    }
  };

  return (
    <AdminLayout currentPath="/admin/melava-campaigns">
      <div className="mc-page">
        <header className="mc-heading">
          <div>
            <h1>Melava Events</h1>
            <p>Create events, set registration and booklet prices, and upload the event payment QR.</p>
          </div>
          <button type="button" className="mc-button secondary" onClick={load} disabled={loading}>
            <RefreshCw size={16} /> Refresh
          </button>
        </header>

        {error ? <div className="mc-alert error" role="alert">{error}</div> : null}
        {notice ? <div className="mc-alert success" role="status">{notice}</div> : null}

        <section className="mc-panel">
          <div className="mc-panel-title">
            <CalendarPlus size={20} />
            <div>
              <h2>{editingId ? "Edit Melava batch" : "Create a Melava batch"}</h2>
              <p>New active batches and their server-managed prices appear on the public registration form.</p>
            </div>
          </div>

          <form className="mc-form" onSubmit={editingId ? saveCampaign : createCampaign}>
            <label>
              Melava number / code <span>*</span>
              <input
                name="melava_code"
                value={form.melava_code}
                onChange={setField}
                placeholder="MM-04"
                pattern="MM-[0-9]{2}"
                maxLength={20}
                required
              />
              <small>Use a unique two-digit number, for example MM-04.</small>
            </label>
            <div className="mc-form-grid">
              <label>
                Event start date
                <input name="start_date" type="date" value={form.start_date} onChange={setField} />
              </label>
              <label>
                Event end date
                <input
                  name="end_date"
                  type="date"
                  min={form.start_date || undefined}
                  value={form.end_date}
                  onChange={setField}
                />
              </label>
            </div>
            <label>
              Melava batch / event title <span>*</span>
              <input name="title" value={form.title} onChange={setField} placeholder="Melava 4 - October 2026" maxLength={255} required />
            </label>
            <label>
              Description
              <textarea name="description" value={form.description} onChange={setField} rows={3} maxLength={5000} />
            </label>
            <div className="mc-form-grid">
              <label>
                Currency
                <select name="currency" value={form.currency} onChange={setField}>
                  <option value="INR">INR - Indian Rupee</option>
                </select>
              </label>
              <label>
                Registration price <span>*</span>
                <input name="registration_fee" type="number" min="0" step="0.01" value={form.registration_fee} onChange={setField} required />
              </label>
              <label className="mc-check">
                <input name="booklet_available" type="checkbox" checked={form.booklet_available} onChange={setField} />
                Offer a booklet for this event
              </label>
              {form.booklet_available ? (
                <label>
                  Booklet price <span>*</span>
                  <input name="booklet_fee" type="number" min="0" step="0.01" value={form.booklet_fee} onChange={setField} required />
                </label>
              ) : null}
            </div>
            <label>
              Payment QR image <span>*</span>
              <div className="mc-upload">
                <ImagePlus size={19} />
                <input name="qr_image" type="file" accept="image/png,image/jpeg,image/webp" onChange={setField} required={!editingId} />
              </div>
              <small>PNG, JPG or WebP; up to 5 MB. This image is uploaded to the backend for this event.</small>
            </label>
            <div className="mc-form-actions">
              <button type="submit" className="mc-button primary" disabled={saving}>
                {saving ? "Saving..." : editingId ? "Save changes" : "Create event"}
              </button>
              {editingId ? (
                <button type="button" className="mc-button secondary" onClick={cancelEdit} disabled={saving}>Cancel</button>
              ) : null}
            </div>
          </form>
        </section>

        <section className="mc-panel">
          <div className="mc-panel-title">
            <div><h2>Existing events</h2><p>Only active events are shown to registrants.</p></div>
          </div>

          {loading ? <p className="mc-muted">Loading events...</p> : null}
          {!loading && campaigns.length === 0 ? <p className="mc-muted">No events have been created yet.</p> : null}
          <div className="mc-list">
            {campaigns.map((campaign) => (
              <article className="mc-event" key={campaign.id}>
                <img src={campaign.qr_image_url} alt="" />
                <div className="mc-event-info">
                  <div className="mc-event-name">
                    <h3>{campaign.melava_code} - {campaign.title}</h3>
                    <span className={`mc-status ${campaign.status}`}>{campaign.status}</span>
                  </div>
                  {campaign.description ? <p>{campaign.description}</p> : null}
                  {campaign.start_date ? (
                    <p>
                      Event date: {campaign.start_date}
                      {campaign.end_date && campaign.end_date !== campaign.start_date ? ` to ${campaign.end_date}` : ""}
                    </p>
                  ) : null}
                  <p className="mc-schedule">Schedule status: {campaign.schedule_status || "Undated"} · Registration: {campaign.status}</p>
                  <div className="mc-prices">
                    <span>Registration: {amount(campaign.registration_fee, campaign.currency)}</span>
                    {campaign.booklet_available
                      ? <span>Booklet: {amount(campaign.booklet_fee, campaign.currency)}</span>
                      : <span>Booklet not offered</span>}
                  </div>
                </div>
                <div className="mc-event-actions">
                  <button
                    type="button"
                    className="mc-button secondary"
                    onClick={() => changeStatus(campaign)}
                    disabled={busyId === campaign.id}
                  >
                    {campaign.status === "active" ? <ToggleRight size={18} /> : <ToggleLeft size={18} />}
                    {busyId === campaign.id
                      ? "Saving..."
                      : campaign.status === "active" ? "Close" : "Open"}
                  </button>
                  <button type="button" className="mc-button secondary" onClick={() => startEdit(campaign)} disabled={busyId === campaign.id}>
                    <Pencil size={16} /> Edit
                  </button>
                  <button type="button" className="mc-button danger" onClick={() => deleteCampaign(campaign)} disabled={busyId === campaign.id}>
                    <Trash2 size={16} /> Delete
                  </button>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>
    </AdminLayout>
  );
}
