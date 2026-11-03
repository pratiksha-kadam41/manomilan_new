import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  CalendarDays,
  CalendarPlus,
  CheckCircle2,
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
import AdminLayout from "../../components/AdminLayout";
import AdminStats from "../../components/AdminStats";
import AdminPagination from "../../components/AdminPagination";
import useDebouncedValue from "../../hooks/useDebouncedValue";
import { request } from "../../../api";
import { useAdminPermission } from "../../../permission-context";
import "../../components/admin-ui.css";
import "../melavaCampaigns.css";
import "./workshopEvents.css";

const EMPTY_FORM = {
  workshop_code: "",
  title: "",
  description: "",
  start_date: "",
  end_date: "",
  currency: "INR",
  registration_fee: "",
  status: "active",
  qr_image: null,
};

const EMPTY_STATS = { total: 0, active: 0, inactive: 0 };

const formatDate = (value) => {
  if (!value) return "Not scheduled";
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

const formatFee = (value, currency) => {
  try {
    return new Intl.NumberFormat("en-IN", {
      style: "currency",
      currency: currency || "INR",
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(Number(value || 0));
  } catch {
    return `${currency || "INR"} ${value || 0}`;
  }
};

export default function WorkshopEvents() {
  const canCreate = useAdminPermission("create", "/admin/workshop-events");
  const canEdit = useAdminPermission("edit", "/admin/workshop-events");
  const canDelete = useAdminPermission("delete", "/admin/workshop-events");
  const canChangeStatus = useAdminPermission("approve", "/admin/workshop-events");
  const [events, setEvents] = useState([]);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [editingId, setEditingId] = useState(null);
  const [formOpen, setFormOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [stats, setStats] = useState(EMPTY_STATS);
  const formRef = useRef(null);
  const formPanelRef = useRef(null);
  const requestId = useRef(0);
  const debouncedSearch = useDebouncedValue(search, 350);

  const load = useCallback(async (targetPage = page) => {
    const id = ++requestId.current;
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ per_page: "10", page: String(targetPage) });
      if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
      if (statusFilter !== "all") params.set("status", statusFilter);
      const response = await request(`/workshop-events?${params}`);
      if (id !== requestId.current) return;
      setEvents(Array.isArray(response?.data) ? response.data : []);
      setPagination(response?.pagination || null);
      setStats({ ...EMPTY_STATS, ...(response?.stats || {}) });
    } catch (requestError) {
      if (id === requestId.current) setError(requestError.message || "Unable to load workshop events.");
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [debouncedSearch, page, statusFilter]);

  useEffect(() => {
    const timer = window.setTimeout(() => load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const setField = (event) => {
    const { name, value, files } = event.target;
    setForm((current) => ({
      ...current,
      [name]: name === "qr_image"
        ? files?.[0] || null
        : name === "workshop_code"
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

  const closeForm = () => {
    setFormOpen(false);
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
    formRef.current?.reset();
  };

  const submitForm = async (event) => {
    event.preventDefault();
    if (saving || busyId !== null) return;
    if (!editingId && !form.qr_image) {
      setError("Upload a QR image for this workshop.");
      return;
    }

    setSaving(true);
    setError("");
    setNotice("");
    const payload = new FormData();
    payload.append("workshop_code", form.workshop_code.trim().toUpperCase());
    payload.append("title", form.title.trim());
    payload.append("description", form.description.trim());
    payload.append("start_date", form.start_date);
    payload.append("end_date", form.end_date);
    payload.append("currency", form.currency.trim().toUpperCase());
    payload.append("registration_fee", form.registration_fee);
    payload.append("status", form.status);
    if (form.qr_image) payload.append("qr_image", form.qr_image);
    if (editingId) payload.append("_method", "PUT");

    try {
      await request(editingId ? `/workshop-events/${editingId}` : "/workshop-events", {
        method: "POST",
        body: payload,
      });
      closeForm();
      setNotice(editingId
        ? "Workshop event updated."
        : "Workshop created and made available according to its status.");
      setPage(1);
      await load(1);
    } catch (requestError) {
      setError(requestError.message || "Unable to save the workshop event.");
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (workshop) => {
    formRef.current?.reset();
    setEditingId(workshop.id);
    setForm({
      workshop_code: workshop.workshop_code || "",
      title: workshop.title || "",
      description: workshop.description || "",
      start_date: workshop.start_date || "",
      end_date: workshop.end_date || "",
      currency: workshop.currency || "INR",
      registration_fee: workshop.registration_fee ?? "",
      status: workshop.status || "active",
      qr_image: null,
    });
    setError("");
    setNotice("");
    setFormOpen(true);
    window.setTimeout(() => formPanelRef.current?.scrollIntoView({ behavior: "smooth", block: "start" }), 0);
  };

  const removeEvent = async (workshop) => {
    if (busyId !== null || saving) return;
    if (!window.confirm(`Delete "${workshop.title}"? Events with registrations cannot be deleted.`)) return;
    setBusyId(workshop.id);
    setError("");
    setNotice("");
    try {
      await request(`/workshop-events/${workshop.id}`, { method: "DELETE" });
      if (editingId === workshop.id) closeForm();
      setNotice("Workshop event deleted.");
      const nextPage = events.length === 1 && page > 1 ? page - 1 : page;
      setPage(nextPage);
      await load(nextPage);
    } catch (requestError) {
      setError(requestError.message || "Unable to delete the workshop event.");
    } finally {
      setBusyId(null);
    }
  };

  const toggleStatus = async (workshop) => {
    if (busyId !== null || saving) return;
    setBusyId(workshop.id);
    setError("");
    setNotice("");
    try {
      await request(`/workshop-events/${workshop.id}/status`, {
        method: "PATCH",
        body: { status: workshop.status === "active" ? "inactive" : "active" },
      });
      setNotice(workshop.status === "active"
        ? "Workshop closed for new registrations."
        : "Workshop opened for new registrations.");
      await load();
    } catch (requestError) {
      setError(requestError.message || "Unable to change workshop status.");
    } finally {
      setBusyId(null);
    }
  };

  const statItems = useMemo(() => [
    { key: "total", label: "Total Workshops", value: stats.total, icon: Layers, tone: "teal" },
    { key: "active", label: "Active", value: stats.active, icon: CheckCircle2, tone: "green", hint: "Open for registration" },
    { key: "inactive", label: "Inactive", value: stats.inactive, icon: XCircle, tone: "amber", hint: "Closed workshops" },
  ], [stats]);

  return (
    <AdminLayout currentPath="/admin/workshop-events">
      <div className="adm-ui mc-page">
        <div className="ui-head">
          <div>
            <h1>Melava Workshop</h1>
            <p>Manage workshop sessions, registration fees, and payment QR codes.</p>
          </div>
          <div className="ui-head-actions">
            {canCreate ? <button type="button" className="ui-btn" onClick={formOpen ? closeForm : openCreateForm} disabled={saving || busyId !== null}>
              {formOpen ? <X size={17} /> : <CalendarPlus size={17} />}
              {formOpen ? "Close form" : "Add workshop"}
            </button> : null}
            <button type="button" className="ui-btn secondary" onClick={() => load()} disabled={loading || saving || busyId !== null}>
              <RefreshCw size={16} className={loading ? "ui-spin" : ""} /> Refresh
            </button>
          </div>
        </div>

        <AdminStats items={statItems} loading={loading && !pagination} label="Workshop event statistics" />
        {error ? <div className="mc-alert error" role="alert">{error}</div> : null}
        {notice ? <div className="mc-alert success" role="status">{notice}</div> : null}

        {formOpen ? (
          <section className="mc-panel mc-form-panel" ref={formPanelRef}>
            <div className="mc-panel-title">
              <CalendarPlus size={20} />
              <div>
                <h2>{editingId ? "Edit workshop" : "Create a workshop"}</h2>
                <p>Active workshops are selectable on the public registration form. Their fee and QR are managed here.</p>
              </div>
            </div>
            <form ref={formRef} className="mc-form" onSubmit={submitForm}>
              <div className="mc-form-grid mc-event-details">
                <label>
                  Workshop code <span>*</span>
                  <input name="workshop_code" value={form.workshop_code} onChange={setField} placeholder="e.g. MW-01" maxLength={20} required />
                </label>
                <label>
                  Workshop title <span>*</span>
                  <input name="title" value={form.title} onChange={setField} placeholder="e.g. Melava Workshop — June" maxLength={255} required />
                </label>
              </div>
              <div className="mc-form-grid mc-event-dates">
                <label>
                  Start date
                  <input name="start_date" type="date" value={form.start_date} onChange={setField} />
                </label>
                <label>
                  End date
                  <input name="end_date" type="date" value={form.end_date} onChange={setField} />
                </label>
              </div>
              <label>
                Description
                <textarea name="description" value={form.description} onChange={setField} maxLength={5000} placeholder="Share the workshop details with registrants." />
              </label>
              <div className="mc-form-grid mc-event-details">
                <label>
                  Currency <span>*</span>
                  <input name="currency" value={form.currency} onChange={setField} maxLength={3} minLength={3} required />
                </label>
                <label>
                  Registration fee <span>*</span>
                  <input name="registration_fee" type="number" min="0" step="0.01" value={form.registration_fee} onChange={setField} required />
                </label>
              </div>
              <div className="mc-form-grid mc-event-details">
                <label>
                  Status <span>*</span>
                  <select name="status" value={form.status} onChange={setField} required>
                    <option value="active">Active — open for registrations</option>
                    <option value="inactive">Inactive — closed</option>
                  </select>
                </label>
                <label className="mc-upload">
                  <ImagePlus size={18} />
                  <span>{editingId ? "Replace payment QR (optional)" : "Payment QR image"} <b>*</b></span>
                  <input name="qr_image" type="file" accept="image/jpeg,image/png,image/webp" onChange={setField} required={!editingId} />
                  <small>JPG, PNG, or WebP; up to 4 MB.</small>
                  {editingId && events.find((item) => item.id === editingId)?.qr_image_url ? (
                    <img className="we-qr-preview" src={events.find((item) => item.id === editingId).qr_image_url} alt="Current payment QR code" />
                  ) : null}
                </label>
              </div>
              <div className="mc-form-actions">
                <button type="submit" className="mc-button primary" disabled={saving || busyId !== null}>
                  {saving ? "Saving…" : editingId ? "Save changes" : "Create workshop"}
                </button>
                <button type="button" className="mc-button secondary" onClick={closeForm} disabled={saving}>Cancel</button>
              </div>
            </form>
          </section>
        ) : null}

        <section className="mc-panel mc-list-panel">
          <div className="mc-panel-title">
            <CalendarDays size={20} />
            <div><h2>Workshop events</h2><p>Only active entries are offered for public registration.</p></div>
          </div>
          <div className="mc-list-tools">
            <label className="mc-search">
              <Search size={17} />
              <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search code or title" aria-label="Search workshops" />
            </label>
            <div className="mc-status-filters" aria-label="Filter by status">
              {["all", "active", "inactive"].map((status) => (
                <button key={status} type="button" className={statusFilter === status ? "active" : ""} onClick={() => { setStatusFilter(status); setPage(1); }}>
                  {status === "all" ? "All" : status}
                </button>
              ))}
            </div>
          </div>
          <div className="mc-table-wrap">
            <table className="mc-table">
              <thead><tr><th>Workshop</th><th>Schedule</th><th>Fee</th><th>Registrations</th><th>Status</th><th className="mc-actions-heading">Actions</th></tr></thead>
              <tbody>
                {loading ? <tr><td colSpan="6">Loading workshops…</td></tr> : null}
                {!loading && events.length === 0 ? <tr><td colSpan="6">No workshops found. Add one to make registration settings dynamic.</td></tr> : null}
                {!loading && events.map((workshop) => (
                  <tr key={workshop.id}>
                    <td data-label="Workshop">
                      <div className="mc-event-cell">
                        <img src={workshop.qr_image_url} alt="" />
                        <div><strong>{workshop.title}</strong><small>{workshop.workshop_code}</small></div>
                      </div>
                    </td>
                    <td data-label="Schedule">
                      <strong>{formatDate(workshop.start_date)}</strong>
                      <small>{workshop.end_date && workshop.end_date !== workshop.start_date ? `to ${formatDate(workshop.end_date)}` : ""}</small>
                    </td>
                    <td data-label="Registration fee"><strong>{formatFee(workshop.registration_fee, workshop.currency)}</strong></td>
                    <td data-label="Registrations">{Number(workshop.registrations_count || 0).toLocaleString("en-IN")}</td>
                    <td data-label="Status"><span className={`mc-status ${workshop.status}`}>{workshop.status}</span></td>
                    <td data-label="Actions">
                      <div className="mc-event-actions">
                        {canEdit ? <button type="button" className="mc-button secondary" onClick={() => startEdit(workshop)} disabled={saving || busyId !== null} aria-label={`Edit ${workshop.title}`}><Pencil size={15} /> Edit</button> : null}
                        {canChangeStatus ? <button type="button" className={`mc-button ${workshop.status === "active" ? "status-active" : "status-inactive"}`} onClick={() => toggleStatus(workshop)} disabled={busyId === workshop.id || saving} aria-label={`${workshop.status === "active" ? "Close" : "Open"} ${workshop.title}`}>
                          {workshop.status === "active" ? <ToggleRight size={16} /> : <ToggleLeft size={16} />}
                          {workshop.status === "active" ? "Close" : "Open"}
                        </button> : null}
                        {canDelete ? <button type="button" className="mc-button danger" onClick={() => removeEvent(workshop)} disabled={busyId === workshop.id || saving || Number(workshop.registrations_count || 0) > 0} aria-label={`Delete ${workshop.title}`} title={Number(workshop.registrations_count || 0) > 0 ? "Workshops with registrations cannot be deleted" : "Delete workshop"}><Trash2 size={15} /> Delete</button> : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {pagination ? (
            <AdminPagination
              page={pagination.current_page}
              lastPage={pagination.last_page}
              from={events.length ? (pagination.current_page - 1) * pagination.per_page + 1 : 0}
              to={(pagination.current_page - 1) * pagination.per_page + events.length}
              total={pagination.total}
              onPage={(nextPage) => { setPage(nextPage); load(nextPage); }}
              disabled={loading}
              label="Workshop pages"
            />
          ) : null}
        </section>
      </div>
    </AdminLayout>
  );
}
