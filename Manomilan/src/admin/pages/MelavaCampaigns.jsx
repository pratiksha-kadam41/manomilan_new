import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  CalendarCheck,
  CalendarDays,
  CalendarPlus,
  CheckCircle2,
  Clock,
  ImagePlus,
  Layers,
  Pencil,
  RefreshCw,
  Search,
  Trash2,
  ToggleLeft,
  ToggleRight,
  X,
  XCircle,
} from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import AdminStats from "../components/AdminStats";
import AdminPagination from "../components/AdminPagination";
import RecordEditorName from "../components/RecordEditorName";
import useDebouncedValue from "../hooks/useDebouncedValue";
import { request } from "../../api";
import { useAdminPermission } from "../../permission-context";
import "../components/admin-ui.css";
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

const EMPTY_STATS = {
  total: 0,
  active: 0,
  inactive: 0,
  upcoming: 0,
  ongoing: 0,
  completed: 0,
  undated: 0,
};

const amount = (value, currency) =>
  new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: currency || "INR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(Number(value || 0));

const formatDate = (value) => {
  if (!value) return "Not scheduled";
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

const scheduleTone = (status) => {
  switch (String(status || "").toLowerCase()) {
    case "ongoing": return "ok";
    case "upcoming": return "info";
    case "completed": return "neutral";
    default: return "neutral";
  }
};

export default function MelavaCampaigns() {
  const canCreate = useAdminPermission("create", "/admin/melava-campaigns");
  const canEdit = useAdminPermission("edit", "/admin/melava-campaigns");
  const canDelete = useAdminPermission("delete", "/admin/melava-campaigns");
  const canChangeStatus = useAdminPermission("approve", "/admin/melava-campaigns");
  const [campaigns, setCampaigns] = useState([]);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [editingId, setEditingId] = useState(null);
  const [formOpen, setFormOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [stats, setStats] = useState(EMPTY_STATS);
  const formRef = useRef(null);
  const formPanelRef = useRef(null);
  const requestId = useRef(0);

  const debouncedSearch = useDebouncedValue(search, 350);
  const activeQuery = debouncedSearch.trim();

  const load = useCallback(async (overrides = {}) => {
    const targetPage = overrides.page ?? page;
    const id = ++requestId.current;
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ per_page: "20", page: String(targetPage) });
      if (activeQuery) params.set("q", activeQuery);
      if (statusFilter !== "all") params.set("status", statusFilter);
      const response = await request(`/melava-campaigns?${params}`);
      if (id !== requestId.current) return;
      setCampaigns(Array.isArray(response?.data) ? response.data : []);
      setPagination(response?.pagination || null);
      setStats({ ...EMPTY_STATS, ...(response?.stats || {}) });
    } catch (requestError) {
      if (id === requestId.current) setError(requestError.message || "Unable to load Melava events.");
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [activeQuery, statusFilter, page]);

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

  const openCreateForm = () => {
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
    formRef.current?.reset();
    setError("");
    setNotice("");
    setFormOpen(true);
    window.setTimeout(() => formPanelRef.current?.scrollIntoView({ behavior: "smooth", block: "start" }), 0);
  };

  const createCampaign = async (event) => {
    event.preventDefault();
    if (saving || busyId !== null) return;
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
      setFormOpen(false);
      setNotice("Melava event created. It is now available on the registration form.");
      setPage(1);
      await load({ page: 1 });
    } catch (requestError) {
      setError(requestError.message || "Unable to create the Melava event.");
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (campaign) => {
    formRef.current?.reset();
    setEditingId(campaign.id);
    setFormOpen(true);
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
    window.setTimeout(() => formPanelRef.current?.scrollIntoView({ behavior: "smooth", block: "start" }), 0);
  };

  const cancelEdit = () => {
    setEditingId(null);
    setFormOpen(false);
    setForm({ ...EMPTY_FORM });
    formRef.current?.reset();
  };

  const saveCampaign = async (event) => {
    event.preventDefault();
    if (!editingId || saving || busyId !== null) return;
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

  const deleteCampaign = async () => {
    if (!deleteTarget || busyId !== null || saving) return;
    const campaign = deleteTarget;
    setBusyId(campaign.id);
    setError("");
    setNotice("");
    try {
      await request(`/melava-campaigns/${campaign.id}`, { method: "DELETE" });
      if (editingId === campaign.id) cancelEdit();
      setDeleteTarget(null);
      setNotice("Melava event deleted.");
      const nextPage = campaigns.length === 1 && page > 1 ? page - 1 : page;
      setPage(nextPage);
      await load({ page: nextPage });
    } catch (requestError) {
      setError(requestError.message || "Unable to delete the Melava event.");
    } finally {
      setBusyId(null);
    }
  };

  const changeStatus = async (campaign) => {
    if (busyId !== null || saving) return;
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
      await load();
    } catch (requestError) {
      setError(requestError.message || "Unable to update event status.");
    } finally {
      setBusyId(null);
    }
  };

  const clearFilters = () => {
    setSearch("");
    setStatusFilter("all");
    setPage(1);
  };

  const hasFilters = Boolean(search.trim() || statusFilter !== "all");

  const statItems = useMemo(() => [
    { key: "total", label: "Total Events", value: stats.total, icon: Layers, tone: "teal", hint: "Matching filters" },
    { key: "active", label: "Active", value: stats.active, icon: CheckCircle2, tone: "green", hint: "Open for registration" },
    { key: "inactive", label: "Inactive", value: stats.inactive, icon: XCircle, tone: "amber", hint: "Closed events" },
    { key: "upcoming", label: "Upcoming", value: stats.upcoming, icon: Clock, tone: "blue", hint: "Scheduled ahead" },
    { key: "ongoing", label: "Ongoing", value: stats.ongoing, icon: CalendarDays, tone: "magenta", hint: "Running today" },
    { key: "completed", label: "Completed", value: stats.completed, icon: CalendarCheck, tone: "slate", hint: "Past events" },
  ], [stats]);

  return (
    <AdminLayout currentPath="/admin/melava-campaigns">
      <div className="adm-ui mc-page">
        <div className="ui-head">
          <div>
            <h1>Melava Events</h1>
            <p>Manage Melava batches, registration prices, and event availability.</p>
          </div>
          <div className="ui-head-actions">
            {canCreate ? <button
              type="button"
              className="ui-btn"
              onClick={() => formOpen
                ? formPanelRef.current?.scrollIntoView({ behavior: "smooth", block: "start" })
                : openCreateForm()}
              disabled={saving || busyId !== null}
            >
              <CalendarPlus size={17} /> {formOpen ? "Form is open" : "Add new Melava"}
            </button> : null}
            <button type="button" className="ui-btn secondary" onClick={() => load()} disabled={loading || saving || busyId !== null}>
              <RefreshCw size={16} className={loading ? "ui-spin" : ""} /> Refresh
            </button>
          </div>
        </div>

        <AdminStats items={statItems} loading={loading && !pagination} label="Melava event statistics" />

        {error ? <div className="ui-alert" role="alert"><span>{error}</span></div> : null}
        {notice ? <div className="ui-alert success" role="status">{notice}</div> : null}

        {formOpen ? (
          <section className="mc-panel mc-form-panel" ref={formPanelRef}>
            <div className="mc-panel-title">
              <CalendarPlus size={20} />
              <div>
                <h2>{editingId ? "Edit Melava batch" : "Create a Melava batch"}</h2>
                <p>New active batches and their server-managed prices appear on the public registration form.</p>
              </div>
            </div>

            <form ref={formRef} className="mc-form" onSubmit={editingId ? saveCampaign : createCampaign}>
              <div className="mc-form-grid mc-event-details">
                <label>
                  Melava number / code <span>*</span>
                  <input
                    name="melava_code"
                    value={form.melava_code}
                    onChange={setField}
                    placeholder="e.g. MM-04"
                    pattern="MM-[0-9]{2}"
                    maxLength={20}
                    required
                  />
                  <small>Use a unique two-digit number, for example MM-04.</small>
                </label>
                <label>
                  Melava batch / event title <span>*</span>
                  <input
                    name="title"
                    value={form.title}
                    onChange={setField}
                    placeholder="e.g. Melava 4 - October 2026"
                    maxLength={255}
                    required
                  />
                </label>
              </div>
              <div className="mc-form-grid mc-event-dates">
                <label>
                  Event start date
                  <input
                    name="start_date"
                    type="date"
                    value={form.start_date}
                    onChange={setField}
                    aria-label="Event start date"
                  />
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
                Description <small className="mc-field-hint">Optional details shown with this event.</small>
                <textarea
                  name="description"
                  value={form.description}
                  onChange={setField}
                  placeholder="Add event details, venue, or any instructions for candidates."
                  rows={3}
                  maxLength={5000}
                />
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
                  <input
                    name="registration_fee"
                    type="number"
                    min="0"
                    step="0.01"
                    value={form.registration_fee}
                    onChange={setField}
                    placeholder="e.g. 500"
                    required
                  />
                </label>
                <label className="mc-check">
                  <input name="booklet_available" type="checkbox" checked={form.booklet_available} onChange={setField} />
                  Offer a booklet for this event
                </label>
                {form.booklet_available ? (
                  <label>
                    Booklet price <span>*</span>
                    <input
                      name="booklet_fee"
                      type="number"
                      min="0"
                      step="0.01"
                      value={form.booklet_fee}
                      onChange={setField}
                      placeholder="e.g. 100"
                      required
                    />
                  </label>
                ) : null}
              </div>
              <label>
                Payment QR image {!editingId ? <span>*</span> : null}
                <div className="mc-upload">
                  <ImagePlus size={19} />
                  <input name="qr_image" type="file" accept="image/png,image/jpeg,image/webp" onChange={setField} required={!editingId} />
                </div>
                <small>
                  {form.qr_image
                    ? `Selected: ${form.qr_image.name}`
                    : editingId
                      ? "Optional when editing. Leave empty to keep the current QR image."
                      : "Choose a PNG, JPG, or WebP image up to 5 MB."}
                </small>
              </label>
              <div className="mc-form-actions">
                <button type="submit" className="ui-btn" disabled={saving || busyId !== null}>
                  {saving ? "Saving..." : editingId ? "Save changes" : "Create event"}
                </button>
                <button type="button" className="ui-btn secondary" onClick={cancelEdit} disabled={saving || busyId !== null}>Cancel</button>
              </div>
            </form>
          </section>
        ) : null}

        <div className="ui-card ui-toolbar">
          <label className="ui-search">
            <Search size={17} />
            <input
              type="search"
              value={search}
              onChange={(event) => { setSearch(event.target.value); setPage(1); }}
              placeholder="Search event code, title, or description"
              aria-label="Search Melava events"
            />
            {search ? (
              <button type="button" onClick={() => { setSearch(""); setPage(1); }} aria-label="Clear search"><X size={16} /></button>
            ) : null}
          </label>
          <select
            className="ui-select"
            value={statusFilter}
            onChange={(event) => { setStatusFilter(event.target.value); setPage(1); }}
            aria-label="Filter events by registration status"
          >
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          {hasFilters ? (
            <button type="button" className="ui-reset" onClick={clearFilters}>Reset</button>
          ) : null}
        </div>

        <div className="ui-card ui-table-card">
          <div className={`ui-table-scroll ${loading ? "is-loading" : ""}`}>
            <table className="ui-table">
              <thead>
                <tr>
                  <th scope="col">Melava event</th>
                  <th scope="col">Schedule</th>
                  <th scope="col">Price</th>
                  <th scope="col">Registration</th>
                  <th scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading && !campaigns.length ? Array.from({ length: 6 }, (_, index) => (
                  <tr className="ui-skeleton-row" key={`skeleton-${index}`}>
                    <td colSpan="5"><span /></td>
                  </tr>
                )) : null}

                {!loading && !campaigns.length ? (
                  <tr>
                    <td colSpan="5">
                      <div className="ui-state">
                        <CalendarDays size={30} />
                        <strong>No events found</strong>
                        <p>{hasFilters ? "No Melava events match your current filters." : "No events have been created yet."}</p>
                        {hasFilters ? <button type="button" className="ui-btn small" onClick={clearFilters}>Clear filters</button> : null}
                      </div>
                    </td>
                  </tr>
                ) : null}

                {campaigns.map((campaign) => (
                  <tr key={campaign.id}>
                    <td>
                      <div className="mc-event-cell">
                        {campaign.qr_image_url ? <img src={campaign.qr_image_url} alt="" loading="lazy" /> : null}
                        <div>
                          <strong>{campaign.melava_code} - {campaign.title}</strong>
                          {campaign.description ? <small>{campaign.description}</small> : null}
                          <RecordEditorName record={campaign} />
                        </div>
                      </div>
                    </td>
                    <td>
                      <strong>{formatDate(campaign.start_date)}</strong>
                      {campaign.end_date && campaign.end_date !== campaign.start_date
                        ? <small>to {formatDate(campaign.end_date)}</small>
                        : null}
                      <span className={`ui-badge ${scheduleTone(campaign.schedule_status)}`}>{campaign.schedule_status || "Undated"}</span>
                    </td>
                    <td>
                      <strong>{amount(campaign.registration_fee, campaign.currency)}</strong>
                      <small>
                        {campaign.booklet_available
                          ? `Booklet: ${amount(campaign.booklet_fee, campaign.currency)}`
                          : "Booklet not offered"}
                      </small>
                    </td>
                    <td>
                      <span className={`ui-badge ${campaign.status === "active" ? "ok" : "off"}`}>{campaign.status}</span>
                    </td>
                    <td>
                      <div className="ui-actions">
                        {canChangeStatus ? <button
                          type="button"
                          className="ui-btn small secondary"
                          onClick={() => changeStatus(campaign)}
                          disabled={busyId !== null || saving}
                          aria-label={`${campaign.status === "active" ? "Deactivate" : "Activate"} ${campaign.melava_code}`}
                        >
                          {campaign.status === "active" ? <ToggleRight size={16} /> : <ToggleLeft size={16} />}
                          {busyId === campaign.id
                            ? "Saving..."
                            : campaign.status === "active" ? "Deactivate" : "Activate"}
                        </button> : null}
                        {canEdit ? <button type="button" className="ui-btn small secondary" onClick={() => startEdit(campaign)} disabled={busyId !== null || saving}>
                          <Pencil size={15} /> Edit
                        </button> : null}
                        {canDelete ? <button type="button" className="ui-btn small danger" onClick={() => setDeleteTarget(campaign)} disabled={busyId !== null || saving}>
                          <Trash2 size={15} /> Delete
                        </button> : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pagination ? (
            <AdminPagination
              page={pagination.current_page || page}
              lastPage={pagination.last_page || 1}
              from={pagination.from}
              to={pagination.to}
              total={pagination.total}
              onPage={setPage}
              disabled={loading}
              label="Melava event pagination"
            />
          ) : null}
        </div>
      </div>

      {deleteTarget ? (
        <div className="mc-modal" onClick={() => !busyId && setDeleteTarget(null)}>
          <section
            className="mc-dialog"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="mc-delete-title"
            onClick={(event) => event.stopPropagation()}
          >
            <button type="button" className="mc-dialog-close" onClick={() => setDeleteTarget(null)} disabled={busyId !== null} aria-label="Close">
              <X size={18} />
            </button>
            <h2 id="mc-delete-title">Delete this Melava event?</h2>
            <p>
              {deleteTarget.melava_code} — {deleteTarget.title}. Events with registrations cannot be deleted.
            </p>
            <div className="mc-dialog-actions">
              <button type="button" className="ui-btn secondary" onClick={() => setDeleteTarget(null)} disabled={busyId !== null}>Cancel</button>
              <button type="button" className="ui-btn danger" onClick={deleteCampaign} disabled={busyId !== null}>
                {busyId !== null ? "Deleting…" : "Delete event"}
              </button>
            </div>
          </section>
        </div>
      ) : null}
    </AdminLayout>
  );
}
