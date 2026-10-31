import { useCallback, useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  FileText,
  RefreshCw,
  Search,
  Wallet,
  X,
} from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import BioDataModal from "./Profiles/BioDataModal.jsx";
import { request } from "../../api";
import "./melavaPayments.css";

const fetchRegistrations = ({ query, booklet, gender, melavaNumber, page, includeFilters }) => {
  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (query) params.set("q", query);
  if (booklet !== "all") params.set("booklet", booklet);
  if (gender !== "all") params.set("gender", gender);
  if (melavaNumber !== "all") params.set("melava_number", melavaNumber);
  if (includeFilters) params.set("include_filters", "1");
  return request(`/melava-registrations?${params}`);
};

const formatDate = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

export default function MelavaPayments() {
  const [registrations, setRegistrations] = useState([]);
  const [stats, setStats] = useState({ total: 0, groom: 0, bride: 0, booklet: 0 });
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [booklet, setBooklet] = useState("all");
  const [gender, setGender] = useState("all");
  const [melavaNumber, setMelavaNumber] = useState("all");
  const [melavaNumbers, setMelavaNumbers] = useState([]);
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selectedRegistration, setSelectedRegistration] = useState(null);
  const [paymentType, setPaymentType] = useState("registration");
  const [paymentVerified, setPaymentVerified] = useState(false);
  const [paymentFile, setPaymentFile] = useState(null);
  const [paymentSaving, setPaymentSaving] = useState(false);
  const [paymentError, setPaymentError] = useState("");
  const [toast, setToast] = useState(null);
  const [bioData, setBioData] = useState(null);
  const requestId = useRef(0);
  const filtersLoaded = useRef(false);

  const load = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    setError("");
    try {
      const response = await fetchRegistrations({
        query,
        booklet,
        gender,
        melavaNumber,
        page,
        includeFilters: !filtersLoaded.current,
      });
      if (id !== requestId.current) return;
      const result = response?.data;
      setRegistrations(Array.isArray(result?.data) ? result.data : []);
      setPagination(result || null);
      setStats(response?.stats || { total: result?.total || 0, groom: 0, bride: 0, booklet: 0 });
      if (!filtersLoaded.current) {
        setMelavaNumbers(Array.isArray(response?.melava_numbers) ? response.melava_numbers : []);
        filtersLoaded.current = true;
      }
    } catch (err) {
      if (id === requestId.current) {
        setError(err.message || "Unable to load Melava registrations.");
      }
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [query, booklet, gender, melavaNumber, page]);

  useEffect(() => {
    const trimmedSearch = search.trim();
    const delay = /^\d+$/.test(trimmedSearch) ? 80 : 180;
    const timer = window.setTimeout(() => {
      setQuery(search.trim());
      setPage(1);
    }, delay);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const openPayment = (row) => {
    setSelectedRegistration(row);
    setPaymentType("registration");
    setPaymentVerified(Boolean(row.payment_verified));
    setPaymentFile(null);
    setPaymentError("");
  };

  const closePayment = () => {
    if (paymentSaving) return;
    setSelectedRegistration(null);
    setPaymentError("");
    setPaymentFile(null);
  };

  const savePayment = async (event) => {
    event.preventDefault();
    if (!selectedRegistration) return;
    setPaymentError("");
    setPaymentSaving(true);
    const body = new FormData();
    body.append("payment_type", paymentType);
    body.append("payment_verified", paymentVerified ? "1" : "0");
    if (paymentFile) body.append("payment_screenshot", paymentFile);

    try {
      const response = await request(`/melava-registrations/${selectedRegistration.id}/payment`, {
        method: "POST",
        body,
      });
      setSelectedRegistration(null);
      setToast({ type: "success", message: response?.message || "Payment updated successfully." });
      await load();
    } catch (err) {
      setPaymentError(err.message || "Unable to update this payment.");
    } finally {
      setPaymentSaving(false);
    }
  };

  const showBiodata = (row, rowNumber) => {
    if (!row.profile_id) return;
    setBioData({ profile: null, profileId: row.profile_id, rowNumber, loading: true, error: "" });

    request(`/profiles/${row.profile_id}/biodata-preview`)
      .then((response) => {
        if (!response?.data) throw new Error("The BioData preview was empty.");
        setBioData((current) => current?.profileId === row.profile_id
          ? { ...current, profile: response.data, loading: false }
          : current);
      })
      .catch((err) => {
        setBioData((current) => current?.profileId === row.profile_id
          ? { ...current, loading: false, error: err.message || "Unable to prepare candidate BioData." }
          : current);
      });
  };

  const openBiodataFullPage = ({ template, orientation }) => {
    if (!bioData) return;
    const cacheKey = `melava-biodata-preview:${bioData.profileId}:${Date.now()}`;
    const urlParams = new URLSearchParams({
      template: template.id,
      orientation,
      sr_no: String(bioData.rowNumber),
      cache_key: cacheKey,
    });
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify(bioData.profile));
    } catch (error) {
      console.error("Could not cache the BioData preview for the full-page view:", error);
    }
    const page = window.open(
      `/admin/melava-biodata/${bioData.profileId}?${urlParams.toString()}`,
      "_blank",
    );
    if (!page) {
      sessionStorage.removeItem(cacheKey);
      setToast({ type: "error", message: "Allow pop-ups to open the full-page BioData preview." });
      return;
    }
    page.opener = null;
    setBioData(null);
  };

  useEffect(() => {
    if (!toast) return undefined;
    const timer = window.setTimeout(() => setToast(null), 4200);
    return () => window.clearTimeout(timer);
  }, [toast]);

  return (
    <AdminLayout currentPath="/admin/melava">
      <div className="mp-page">
        <div className="mp-head">
          <div>
            <h1>Melava Registrations</h1>
            <p>Manage candidate registrations, biodata, and Melava payments.</p>
          </div>
          <button type="button" className="mp-btn mp-btn-ghost" onClick={load} disabled={loading}>
            <RefreshCw size={16} className={loading ? "mp-spin" : ""} /> Refresh
          </button>
        </div>

        <div className="mp-stats" aria-label="Melava registration statistics">
          <article className="mp-stat"><span>Total registrations</span><strong>{stats.total.toLocaleString("en-IN")}</strong></article>
          <article className="mp-stat"><span>Grooms</span><strong>{stats.groom.toLocaleString("en-IN")}</strong></article>
          <article className="mp-stat"><span>Brides</span><strong>{stats.bride.toLocaleString("en-IN")}</strong></article>
          <article className="mp-stat"><span>Booklet requests</span><strong>{stats.booklet.toLocaleString("en-IN")}</strong></article>
        </div>

        <div className="mp-tools">
          <label className="mp-search">
            <Search size={17} />
            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search registration no., name, mobile or city"
              aria-label="Search Melava registrations"
            />
          </label>
          <label className="mp-booklet">
            <span>Booklet</span>
            <select
              value={booklet}
              onChange={(event) => {
                setBooklet(event.target.value);
                setPage(1);
              }}
              aria-label="Filter by booklet"
            >
              <option value="all">All users</option>
              <option value="yes">Booklet requested</option>
              <option value="no">No booklet</option>
            </select>
          </label>
          <label className="mp-filter">
            <span>Groom / Bride</span>
            <select
              value={gender}
              onChange={(event) => {
                setGender(event.target.value);
                setPage(1);
              }}
              aria-label="Filter by groom or bride"
            >
              <option value="all">All</option>
              <option value="groom">Groom</option>
              <option value="bride">Bride</option>
            </select>
          </label>
          <label className="mp-filter">
            <span>Melava number</span>
            <select
              value={melavaNumber}
              onChange={(event) => {
                setMelavaNumber(event.target.value);
                setPage(1);
              }}
              aria-label="Filter by Melava number"
            >
              <option value="all">All Melavas</option>
              {melavaNumbers.map((number) => (
                <option key={number} value={number}>{number}</option>
              ))}
            </select>
          </label>
        </div>

        {error ? (
          <div className="mp-alert er" role="alert">
            <span>{error}</span>
            <button type="button" className="mp-btn mp-btn-ghost" onClick={load} disabled={loading}>Try again</button>
          </div>
        ) : null}

        <div className="mp-card">
          <div className="mp-table-scroll">
            <table className="mp-table">
              <thead>
                <tr>
                  <th>Candidate</th>
                  <th>Type</th>
                  <th>Melava number</th>
                  <th>City</th>
                  <th>Registration type</th>
                  <th>Date of registration</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading && !registrations.length ? Array.from({ length: 5 }, (_, index) => (
                  <tr className="mp-skeleton" key={`skeleton-${index}`}>
                    <td colSpan="7"><span /></td>
                  </tr>
                )) : null}
                {loading && registrations.length ? (
                  <tr className="mp-updating-row"><td colSpan="7">Updating results…</td></tr>
                ) : null}
                {!loading && !registrations.length ? (
                  <tr><td className="mp-empty" colSpan="7">No Melava registrations match this view.</td></tr>
                ) : null}
                {registrations.map((row, index) => (
                  <tr key={row.id}>
                    <td>
                      <div className="mp-candidate">
                        {row.profile_photo_url ? (
                          <img src={row.profile_photo_url} alt="" className="mp-avatar" loading="lazy" />
                        ) : (
                          <span className="mp-avatar mp-avatar-fallback" aria-hidden="true">
                            {(row.fullName || row.profile?.fullName || "?").trim().charAt(0).toUpperCase()}
                          </span>
                        )}
                        <strong>{row.fullName || row.profile?.fullName || "Unnamed candidate"}</strong>
                      </div>
                    </td>
                    <td><span className={`mp-gender ${String(row.groomOrBride).toLowerCase() === "bride" ? "bride" : "groom"}`}>{row.groomOrBride || "-"}</span></td>
                    <td><span className="mp-number-chip">{row.melava_number || row.melava_title || "-"}</span></td>
                    <td>{row.profile?.professionalCity || row.city || "-"}</td>
                    <td>{row.profile?.registration_type === "existing" ? "Existing" : "Melava"}</td>
                    <td>{formatDate(row.melava_registered_at || row.created_at)}</td>
                    <td>
                      <div className="mp-actions">
                        <button
                          type="button"
                          className="mp-btn mp-btn-biodata"
                          onClick={() => showBiodata(row, (pagination?.from || 1) + index)}
                          disabled={!row.profile_id}
                          title={row.profile_id
                            ? "Choose a template and preview candidate BioData"
                            : "A candidate profile is not linked to this registration"}
                        >
                          <FileText size={15} /> BioData
                        </button>
                        <button type="button" className="mp-btn mp-btn-payment" onClick={() => openPayment(row)}>
                          <Wallet size={15} /> Payment
                        </button>
                        <Link className="mp-btn mp-btn-edit" to={`/melava-registration?admin_edit=${row.id}`}>Edit</Link>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {pagination && pagination.last_page > 1 ? (
          <nav className="mp-pagination" aria-label="Pagination">
            <span>
              Showing {pagination.from ?? 0}–{pagination.to ?? 0} of {pagination.total.toLocaleString("en-IN")}
            </span>
            <div>
              <button type="button" onClick={() => setPage((value) => value - 1)} disabled={page <= 1 || loading} aria-label="Previous page">
                <ChevronLeft size={17} />
              </button>
              <span>Page {page} of {pagination.last_page}</span>
              <button type="button" onClick={() => setPage((value) => value + 1)} disabled={page >= pagination.last_page || loading} aria-label="Next page">
                <ChevronRight size={17} />
              </button>
            </div>
          </nav>
        ) : null}
      </div>

      {selectedRegistration ? (
        <div className="mp-modal" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget) closePayment();
        }}>
          <section className="mp-dialog mp-payment-dialog" role="dialog" aria-modal="true" aria-labelledby="mp-payment-title">
            <button type="button" className="mp-dialog-close" onClick={closePayment} disabled={paymentSaving} aria-label="Close payment dialog">
              <X size={18} />
            </button>
            <h2 id="mp-payment-title">Add or update payment</h2>
            <p>{selectedRegistration.fullName || selectedRegistration.profile?.fullName || "Candidate"} · {selectedRegistration.username || ""}</p>
            <form onSubmit={savePayment}>
              <label className="mp-field">
                <span>Payment category</span>
                <select
                  value={paymentType}
                  onChange={(event) => {
                    const nextType = event.target.value;
                    setPaymentType(nextType);
                    setPaymentVerified(Boolean(nextType === "booklet"
                      ? selectedRegistration.booklet_payment_verified
                      : selectedRegistration.payment_verified));
                    setPaymentFile(null);
                  }}
                  disabled={paymentSaving}
                >
                  <option value="registration">Melava registration</option>
                  {selectedRegistration.is_booklet_req ? <option value="booklet">Booklet</option> : null}
                </select>
              </label>
              <div className="mp-current-payment">
                <span>Current payment proof</span>
                {(paymentType === "booklet"
                  ? selectedRegistration.booklet_payment_screenshot_url
                  : selectedRegistration.payment_screenshot_url) ? (
                  <a
                    href={paymentType === "booklet"
                      ? selectedRegistration.booklet_payment_screenshot_url
                      : selectedRegistration.payment_screenshot_url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    <Download size={15} /> View current proof
                  </a>
                ) : <span className="mp-muted">No proof uploaded</span>}
              </div>
              <label className="mp-field">
                <span>Upload or replace proof <small>(JPG, PNG, WebP or PDF; max 5 MB)</small></span>
                <input
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,.pdf"
                  onChange={(event) => setPaymentFile(event.target.files?.[0] || null)}
                  disabled={paymentSaving}
                />
              </label>
              <label className="mp-field">
                <span>Payment status</span>
                <select
                  value={paymentVerified ? "verified" : "pending"}
                  onChange={(event) => setPaymentVerified(event.target.value === "verified")}
                  disabled={paymentSaving}
                >
                  <option value="pending">Pending</option>
                  <option value="verified">Verified</option>
                </select>
              </label>
              {paymentError ? <div className="mp-alert er" role="alert">{paymentError}</div> : null}
              <div className="mp-dialog-actions">
                <button type="button" className="mp-btn mp-btn-ghost" onClick={closePayment} disabled={paymentSaving}>Cancel</button>
                <button type="submit" className="mp-btn mp-btn-green" disabled={paymentSaving}>
                  {paymentSaving ? "Saving..." : "Save payment"}
                </button>
              </div>
            </form>
          </section>
        </div>
      ) : null}

      {bioData ? (
        <BioDataModal
          profile={bioData.profile}
          onClose={() => setBioData(null)}
          onOpenFullScreen={openBiodataFullPage}
          loading={bioData.loading}
          loadError={bioData.error}
        />
      ) : null}

      {toast ? <div className={`mp-toast ${toast.type}`} role="status">{toast.message}</div> : null}
    </AdminLayout>
  );
}
