import { useCallback, useEffect, useState } from "react";
import {
  BadgeCheck,
  CalendarDays,
  Check,
  Clock3,
  Eye,
  FileImage,
  MapPin,
  RefreshCw,
  Search,
  UserRound,
  Users,
  X,
  XCircle,
} from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import AdminPagination from "../components/AdminPagination";
import { useAdminPermission } from "../../permission-context";
import { apiMediaUrl, request } from "../../api";
import "../components/admin-ui.css";
import "./workshopRegistrations.css";

const EMPTY_STATS = { total: 0, pending: 0, verified: 0, rejected: 0 };

const formatFee = (value, currency) => {
  if (value === null || value === undefined) return "—";
  try {
    return new Intl.NumberFormat("en-IN", {
      style: "currency",
      currency: currency || "INR",
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(Number(value));
  } catch {
    return `${currency || "INR"} ${value}`;
  }
};

const formatDate = (value, includeTime = false) => {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("en-IN", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    ...(includeTime ? { hour: "2-digit", minute: "2-digit", hour12: true } : {}),
  }).format(date);
};

function StatusBadge({ status }) {
  const normalized = String(status || "Pending").toLowerCase();
  const Icon = normalized === "verified" ? BadgeCheck : normalized === "rejected" ? XCircle : Clock3;
  return (
    <span className={`wr-status ${normalized}`}>
      <Icon size={14} />
      {status || "Pending"}
    </span>
  );
}

function Detail({ label, value }) {
  return (
    <div className="wr-detail">
      <span>{label}</span>
      <strong>{value || "—"}</strong>
    </div>
  );
}

export default function WorkshopRegistrations() {
  const canApprove = useAdminPermission("approve", "/admin/workshop");
  const [registrations, setRegistrations] = useState([]);
  const [stats, setStats] = useState(EMPTY_STATS);
  const [filters, setFilters] = useState({ name: "", mobile: "", city: "", status: "" });
  const [appliedFilters, setAppliedFilters] = useState({ name: "", mobile: "", city: "", status: "" });
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selected, setSelected] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState("");
  const [remarks, setRemarks] = useState("");
  const [savingStatus, setSavingStatus] = useState(false);
  const [toast, setToast] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ page: String(page), per_page: "20" });
      Object.entries(appliedFilters).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });
      const response = await request(`/workshop-registrations?${params.toString()}`);
      const result = response?.data;
      setRegistrations(Array.isArray(result?.data) ? result.data : []);
      setPagination(result || null);
      setStats({ ...EMPTY_STATS, ...(response?.stats || {}) });
    } catch (loadError) {
      setError(loadError.message || "Unable to load workshop registrations.");
    } finally {
      setLoading(false);
    }
  }, [page, appliedFilters]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  useEffect(() => {
    if (!toast) return undefined;
    const timer = window.setTimeout(() => setToast(""), 3500);
    return () => window.clearTimeout(timer);
  }, [toast]);

  useEffect(() => {
    if (!selected) return undefined;
    const onKeyDown = (event) => {
      if (event.key === "Escape" && !savingStatus) setSelected(null);
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [selected, savingStatus]);

  const openDetails = async (registration) => {
    setSelected(registration);
    setRemarks(registration.remarks || "");
    setDetailError("");
    setDetailLoading(true);
    try {
      const response = await request(`/workshop-registrations/${registration.id}`);
      setSelected(response?.data || registration);
      setRemarks(response?.data?.remarks || "");
    } catch (detailRequestError) {
      setDetailError(detailRequestError.message || "Unable to load registration details.");
    } finally {
      setDetailLoading(false);
    }
  };

  const updateStatus = async (status) => {
    if (!selected) return;
    setSavingStatus(true);
    setDetailError("");
    try {
      const response = await request(`/workshop-registrations/${selected.id}/status`, {
        method: "PATCH",
        body: { status, remarks: status === "Rejected" ? remarks : "" },
      });
      setSelected((current) => ({ ...current, ...(response?.data || {}), payment_screenshot_url: current?.payment_screenshot_url }));
      setToast(response?.message || `Registration ${status.toLowerCase()}.`);
      await load();
    } catch (statusError) {
      setDetailError(statusError.message || "Unable to update registration status.");
    } finally {
      setSavingStatus(false);
    }
  };

  const applyFilters = (event) => {
    event.preventDefault();
    setPage(1);
    setAppliedFilters({ ...filters });
  };

  const resetFilters = () => {
    const empty = { name: "", mobile: "", city: "", status: "" };
    setFilters(empty);
    setAppliedFilters(empty);
    setPage(1);
  };

  return (
    <AdminLayout currentPath="/admin/workshop">
      <div className="adm-ui wr-page">
        <header className="ui-head">
          <div>
            <h1>Workshop Registrations</h1>
            <p>Review candidate details and verify their payment submissions.</p>
          </div>
          <div className="ui-head-actions">
            <button type="button" className="ui-btn secondary" onClick={load} disabled={loading}>
              <RefreshCw size={15} className={loading ? "wr-spin" : ""} />
              {loading ? "Refreshing…" : "Refresh"}
            </button>
          </div>
        </header>

        {toast ? <div className="wr-toast" role="status">{toast}</div> : null}
        {error ? <div className="wr-alert" role="alert">{error}<button type="button" onClick={load}>Try again</button></div> : null}

        <section className="ui-stats" aria-label="Registration summary">
          {[
            ["Total Candidates", stats.total, Users, "teal"],
            ["Pending Review", stats.pending, Clock3, "amber"],
            ["Verified", stats.verified, BadgeCheck, "green"],
            ["Rejected", stats.rejected, XCircle, "red"],
          ].map(([label, value, Icon, tone]) => (
            <article className={`ui-stat ${tone}`} key={label}>
              <div className="ui-stat-body">
                <span className="ui-stat-label">{label}</span>
                <strong className="ui-stat-value">{Number(value || 0).toLocaleString("en-IN")}</strong>
              </div>
              <span className="ui-stat-icon"><Icon size={20} /></span>
            </article>
          ))}
        </section>

        <section className="ui-card wr-list-card">
          <div className="wr-card-heading">
            <div>
              <h2>Workshop Candidate List</h2>
              <p>Search and manage submitted registrations.</p>
            </div>
            <span className="wr-total"><Users size={15} /> {Number(pagination?.total || 0).toLocaleString("en-IN")} candidates</span>
          </div>

          <form className="wr-filters" onSubmit={applyFilters}>
            <label>
              Candidate Name
              <span className="wr-input-wrap"><UserRound size={15} /><input value={filters.name} onChange={(event) => setFilters({ ...filters, name: event.target.value })} placeholder="Enter name" /></span>
            </label>
            <label>
              Mobile
              <input value={filters.mobile} onChange={(event) => setFilters({ ...filters, mobile: event.target.value.replace(/\D/g, "").slice(0, 15) })} inputMode="numeric" placeholder="Mobile number" />
            </label>
            <label>
              City
              <span className="wr-input-wrap"><MapPin size={15} /><input value={filters.city} onChange={(event) => setFilters({ ...filters, city: event.target.value })} placeholder="City" /></span>
            </label>
            <label>
              Status
              <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
                <option value="">All statuses</option>
                <option value="Pending">Pending</option>
                <option value="Verified">Verified</option>
                <option value="Rejected">Rejected</option>
              </select>
            </label>
            <div className="wr-filter-actions">
              <button type="submit" className="ui-btn"><Search size={15} /> Search</button>
              <button type="button" className="ui-btn secondary" onClick={resetFilters}>Reset</button>
            </div>
          </form>

          <div className={`ui-table-scroll wr-table-scroll${loading ? " is-loading" : ""}`}>
            <table className="ui-table wr-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Candidate</th>
                  <th>Workshop</th>
                  <th>Mobile</th>
                  <th>WhatsApp</th>
                  <th>City</th>
                  <th>District</th>
                  <th>Education</th>
                  <th>Status</th>
                  <th>Registered</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {!loading && registrations.map((registration, index) => (
                  <tr key={registration.id}>
                    <td>{(Number(pagination?.from || 1) - 1) + index + 1}</td>
                    <td><strong className="wr-candidate-name">{registration.full_name}</strong></td>
                    <td>{registration.workshop_event?.title || "Legacy registration"}{registration.registration_fee !== null && registration.registration_fee !== undefined ? <small className="wr-registration-fee">{formatFee(registration.registration_fee, registration.registration_currency)}</small> : null}</td>
                    <td><a className="wr-phone" href={`tel:${registration.mobile_number}`}>{registration.mobile_number}</a></td>
                    <td>{registration.whatsapp_number || "—"}</td>
                    <td>{registration.city || "—"}</td>
                    <td>{registration.district || "—"}</td>
                    <td className="wr-education">{registration.education || "—"}</td>
                    <td><StatusBadge status={registration.status} /></td>
                    <td className="wr-date">{formatDate(registration.created_at)}</td>
                    <td>
                      <button type="button" className="ui-btn small" onClick={() => openDetails(registration)}>
                        <Eye size={14} /> View
                      </button>
                    </td>
                  </tr>
                ))}
                {!loading && !error && registrations.length === 0 ? (
                  <tr><td className="wr-empty" colSpan="11">No workshop registrations found for these filters.</td></tr>
                ) : null}
                {loading ? <tr><td className="wr-empty" colSpan="11">Loading registrations…</td></tr> : null}
              </tbody>
            </table>
          </div>
          <AdminPagination
            page={Number(pagination?.current_page || page)}
            lastPage={Number(pagination?.last_page || 1)}
            from={pagination?.from || 0}
            to={pagination?.to || 0}
            total={pagination?.total || 0}
            onPage={setPage}
            disabled={loading}
            label="Workshop registrations pages"
          />
        </section>

        {selected ? (
          <div className="wr-modal-backdrop" onMouseDown={(event) => {
            if (event.target === event.currentTarget && !savingStatus) setSelected(null);
          }}>
            <section className="wr-modal" role="dialog" aria-modal="true" aria-labelledby="wr-modal-title">
              <header className="wr-modal-head">
                <div>
                  <span className="wr-modal-eyebrow">Registration #{selected.id}</span>
                  <h2 id="wr-modal-title">{selected.full_name || "Candidate Details"}</h2>
                </div>
                <button type="button" className="wr-close" onClick={() => setSelected(null)} disabled={savingStatus} aria-label="Close details"><X size={20} /></button>
              </header>
              {detailError ? <div className="wr-alert" role="alert">{detailError}</div> : null}
              {detailLoading ? <div className="wr-modal-loading">Loading candidate details…</div> : (
                <>
                  <div className="wr-modal-status"><StatusBadge status={selected.status} /><span><CalendarDays size={15} /> Registered {formatDate(selected.created_at, true)}</span></div>
                  <div className="wr-detail-grid">
                    <Detail label="Mobile Number" value={selected.mobile_number} />
                    <Detail label="WhatsApp Number" value={selected.whatsapp_number} />
                    <Detail label="Date of Birth" value={formatDate(selected.date_of_birth)} />
                    <Detail label="Education" value={selected.education} />
                    <Detail label="Marital Status" value={selected.marital_status} />
                    <Detail label="City" value={selected.city} />
                    <Detail label="District" value={selected.district} />
                    <Detail label="Parent / Guardian" value={selected.parent_name} />
                    <Detail label="Parent Contact" value={selected.parent_contact_number} />
                    <Detail label="Transaction / UTR" value={selected.legacy_transaction_id || selected.transaction_id} />
                    <Detail label="Workshop" value={selected.workshop_event?.title || "Legacy registration"} />
                    <Detail label="Registration fee" value={formatFee(selected.registration_fee, selected.registration_currency)} />
                  </div>
                  <div className="wr-payment-preview">
                    <h3><FileImage size={17} /> Payment Screenshot</h3>
                    {selected.payment_screenshot_url ? (
                      <a href={apiMediaUrl(selected.payment_screenshot_url)} target="_blank" rel="noreferrer">
                        <img src={apiMediaUrl(selected.payment_screenshot_url)} alt={`Payment screenshot for ${selected.full_name}`} />
                        <span>Open full size</span>
                      </a>
                    ) : <p>Payment screenshot unavailable.</p>}
                  </div>
                  {selected.status === "Rejected" && selected.remarks ? (
                    <div className="wr-remarks-view"><strong>Rejection remarks</strong><p>{selected.remarks}</p></div>
                  ) : null}
                  {canApprove && selected.status === "Pending" ? (
                    <div className="wr-review-actions">
                      <label>
                        Rejection remarks <span>(optional)</span>
                        <textarea value={remarks} onChange={(event) => setRemarks(event.target.value)} maxLength={2000} rows={2} placeholder="Add a reason if rejecting this registration" />
                      </label>
                      <div>
                        <button type="button" className="ui-btn danger" onClick={() => updateStatus("Rejected")} disabled={savingStatus}>
                          <XCircle size={15} /> {savingStatus ? "Saving…" : "Reject"}
                        </button>
                        <button type="button" className="ui-btn" onClick={() => updateStatus("Verified")} disabled={savingStatus}>
                          <Check size={15} /> {savingStatus ? "Saving…" : "Verify Registration"}
                        </button>
                      </div>
                    </div>
                  ) : null}
                </>
              )}
            </section>
          </div>
        ) : null}
      </div>
    </AdminLayout>
  );
}
