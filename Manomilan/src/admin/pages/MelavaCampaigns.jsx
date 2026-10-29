import { useCallback, useEffect, useState } from "react";
import { CalendarPlus, ImagePlus, RefreshCw, ToggleLeft, ToggleRight } from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { request } from "../../api";
import "./melavaCampaigns.css";

const EMPTY_FORM = {
  title: "",
  description: "",
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
    payload.append("title", form.title.trim());
    payload.append("description", form.description.trim());
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
              <h2>Create a Melava batch</h2>
              <p>New active batches and their server-managed prices appear on the public registration form.</p>
            </div>
          </div>

          <form className="mc-form" onSubmit={createCampaign}>
            <label>
              Melava batch / event title <span>*</span>
              <input name="title" value={form.title} onChange={setField} maxLength={255} required />
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
                <input name="qr_image" type="file" accept="image/png,image/jpeg,image/webp" onChange={setField} required />
              </div>
              <small>PNG, JPG or WebP; up to 5 MB. This image is uploaded to the backend for this event.</small>
            </label>
            <button type="submit" className="mc-button primary" disabled={saving}>
              {saving ? "Creating event..." : "Create event"}
            </button>
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
                    <h3>{campaign.title}</h3>
                    <span className={`mc-status ${campaign.status}`}>{campaign.status}</span>
                  </div>
                  {campaign.description ? <p>{campaign.description}</p> : null}
                  <div className="mc-prices">
                    <span>Registration: {amount(campaign.registration_fee, campaign.currency)}</span>
                    {campaign.booklet_available
                      ? <span>Booklet: {amount(campaign.booklet_fee, campaign.currency)}</span>
                      : <span>Booklet not offered</span>}
                  </div>
                </div>
                <button
                  type="button"
                  className={`mc-button ${campaign.status === "active" ? "secondary" : "primary"}`}
                  onClick={() => changeStatus(campaign)}
                  disabled={busyId === campaign.id}
                >
                  {campaign.status === "active" ? <ToggleRight size={18} /> : <ToggleLeft size={18} />}
                  {busyId === campaign.id
                    ? "Saving..."
                    : campaign.status === "active" ? "Close event" : "Open event"}
                </button>
              </article>
            ))}
          </div>
        </section>
      </div>
    </AdminLayout>
  );
}
