import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  BadgeCheck,
  BookOpen,
  Clock,
  FileText,
  Heart,
  RefreshCw,
  Search,
  User,
  Users,
  Wallet,
  X,
} from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import AdminStats from "../components/AdminStats";
import AdminPagination from "../components/AdminPagination";
import AgeRangeFilter from "../components/AgeRangeFilter";
import RecordEditorName from "../components/RecordEditorName";
import useDebouncedValue from "../hooks/useDebouncedValue";
import { useAdminPermission } from "../../permission-context";
import BioDataModal from "./Profiles/BioDataModal.jsx";
import { request, apiMediaUrl, getProfilePhoto } from "../../api";
import "../components/admin-ui.css";
import "./melavaPayments.css";

const EMPTY_STATS = {
  total: 0,
  groom: 0,
  bride: 0,
  booklet: 0,
  verified: 0,
  pending: 0,
  attended: 0,
};

const fetchRegistrations = ({ query, booklet, gender, melavaNumber, age, page, includeFilters }) => {
  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (query) params.set("q", query);
  if (booklet !== "all") params.set("booklet", booklet);
  if (gender !== "all") params.set("gender", gender);
  if (melavaNumber !== "all") params.set("melava_number", melavaNumber);
  if (age.from) params.set("age_from", age.from);
  if (age.to) params.set("age_to", age.to);
  if (includeFilters) params.set("include_filters", "1");
  return request(`/melava-registrations?${params}`);
};

const formatDate = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

const initials = (value) => String(value || "")
  .trim()
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part[0].toUpperCase())
  .join("") || "?";

export default function MelavaPayments() {
  const canApprovePayments = useAdminPermission("approve", "/admin/melava");
  const canExportBiodata = useAdminPermission("export", null, "admin_side_profile");
  const [registrations, setRegistrations] = useState([]);
  const [stats, setStats] = useState(EMPTY_STATS);
  const [search, setSearch] = useState("");
  const [booklet, setBooklet] = useState("all");
  const [gender, setGender] = useState("all");
  const [melavaNumber, setMelavaNumber] = useState("all");
  const [melavaNumbers, setMelavaNumbers] = useState([]);
  const [ageInput, setAgeInput] = useState({ from: "", to: "" });
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selectedRegistration, setSelectedRegistration] = useState(null);
  const [paymentType, setPaymentType] = useState("registration");
  const [paymentVerified, setPaymentVerified] = useState(false);
  const [paymentFile, setPaymentFile] = useState(null);
  const [paymentSaving, setPaymentSaving] = useState(false);
  const [paymentDecision, setPaymentDecision] = useState(null);
  const [paymentError, setPaymentError] = useState("");
  const [toast, setToast] = useState(null);
  const [bioData, setBioData] = useState(null);
  const requestId = useRef(0);
  const filtersLoaded = useRef(false);

  const debouncedSearch = useDebouncedValue(search, 300);
  const debouncedAge = useDebouncedValue(ageInput, 400);
  const activeQuery = debouncedSearch.trim();
  const ageFrom = debouncedAge.from;
  const ageTo = debouncedAge.to;
  const ageError = ageFrom !== "" && ageTo !== "" && Number(ageFrom) > Number(ageTo)
    ? "Minimum age cannot be greater than maximum age."
    : "";

  const load = useCallback(async () => {
    const id = ++requestId.current;
    if (ageError) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const response = await fetchRegistrations({
        query: activeQuery,
        booklet,
        gender,
        melavaNumber,
        age: { from: ageFrom, to: ageTo },
        page,
        includeFilters: !filtersLoaded.current,
      });
      if (id !== requestId.current) return;
      const result = response?.data;
      setRegistrations(Array.isArray(result?.data) ? result.data : []);
      setPagination(result || null);
      setStats({ ...EMPTY_STATS, ...(response?.stats || {}) });
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
  }, [activeQuery, booklet, gender, melavaNumber, ageFrom, ageTo, ageError, page]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const hasFilters = Boolean(
    activeQuery || booklet !== "all" || gender !== "all" || melavaNumber !== "all" || ageFrom || ageTo,
  );

  const clearFilters = () => {
    setSearch("");
    setBooklet("all");
    setGender("all");
    setMelavaNumber("all");
    setAgeInput({ from: "", to: "" });
    setPage(1);
  };

  const openPayment = (row) => {
    setSelectedRegistration(row);
    const defaultType = row.is_booklet_req ? "both" : "registration";
    setPaymentType(defaultType);
    setPaymentVerified(defaultType === "both"
      ? Boolean(row.payment_verified && row.booklet_payment_verified)
      : Boolean(row.payment_verified));
    setPaymentFile(null);
    setPaymentError("");
  };

  const closePayment = () => {
    if (paymentSaving) return;
    setSelectedRegistration(null);
    setPaymentError("");
    setPaymentFile(null);
  };

  const savePayment = async (event, verified = paymentVerified) => {
    event?.preventDefault();
    if (!selectedRegistration) return;
    setPaymentError("");
    setPaymentSaving(true);
    setPaymentDecision(verified ? "verify" : "reject");
    const body = new FormData();
    body.append("payment_type", paymentType);
    body.append("payment_verified", verified ? "1" : "0");
    if (paymentFile) body.append("payment_screenshot", paymentFile);

    try {
      const response = await request(`/melava-registrations/${selectedRegistration.id}/payment`, {
        method: "POST",
        body,
      });
      setSelectedRegistration(null);
      setToast({
        type: "success",
        message: response?.message || (verified
          ? "Payment proof verified successfully."
          : "Payment proof rejected and marked unverified."),
      });
      await load();
    } catch (err) {
      setPaymentError(err.message || "Unable to update this payment.");
    } finally {
      setPaymentSaving(false);
      setPaymentDecision(null);
    }
  };

  const showBiodata = (row, rowNumber) => {
    if (!canExportBiodata || !row.profile_id) return;
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
    if (!canExportBiodata || !bioData) return;
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

  const statItems = useMemo(() => [
    { key: "total", label: "Total Registrations", value: stats.total, icon: Users, tone: "teal", hint: "Matching filters" },
    { key: "verified", label: "Approved Payments", value: stats.verified, icon: BadgeCheck, tone: "green", hint: "Payment verified" },
    { key: "pending", label: "Pending Payments", value: stats.pending, icon: Clock, tone: "amber", hint: "Awaiting review" },
    { key: "booklet", label: "Booklet Requests", value: stats.booklet, icon: BookOpen, tone: "magenta", hint: "Booklet requested" },
    { key: "groom", label: "Grooms", value: stats.groom, icon: User, tone: "blue", hint: "Male candidates" },
    { key: "bride", label: "Brides", value: stats.bride, icon: Heart, tone: "magenta", hint: "Female candidates" },
  ], [stats]);
  const currentPaymentProofs = selectedRegistration
    ? (paymentType === "booklet"
      ? [{ label: "Booklet", url: selectedRegistration.booklet_payment_screenshot_url }]
      : paymentType === "both"
        ? [
          { label: "Melava registration", url: selectedRegistration.payment_screenshot_url },
          { label: "Booklet", url: selectedRegistration.booklet_payment_screenshot_url },
        ]
        : [{ label: "Melava registration", url: selectedRegistration.payment_screenshot_url }])
      .filter((proof) => proof.url)
      .filter((proof, index, proofs) => proofs.findIndex((item) => item.url === proof.url) === index)
    : [];

  return (
    <AdminLayout currentPath="/admin/melava">
      <div className="adm-ui mp-page">
        <div className="ui-head">
          <div>
            <h1>Melava Registrations</h1>
            <p>Manage candidate registrations, biodata, and Melava payments.</p>
          </div>
          <div className="ui-head-actions">
            <button type="button" className="ui-btn secondary" onClick={load} disabled={loading}>
              <RefreshCw size={16} className={loading ? "ui-spin" : ""} /> Refresh
            </button>
          </div>
        </div>

        <AdminStats items={statItems} loading={loading && !pagination} label="Melava registration statistics" />

        <div className="ui-card ui-toolbar">
          <label className="ui-search">
            <Search size={17} />
            <input
              type="search"
              value={search}
              onChange={(event) => { setSearch(event.target.value); setPage(1); }}
              placeholder="Search registration no., name, mobile or city"
              aria-label="Search Melava registrations"
            />
            {search ? (
              <button type="button" onClick={() => { setSearch(""); setPage(1); }} aria-label="Clear search"><X size={16} /></button>
            ) : null}
          </label>
          <select
            className="ui-select"
            value={booklet}
            onChange={(event) => { setBooklet(event.target.value); setPage(1); }}
            aria-label="Filter by booklet"
          >
            <option value="all">All users</option>
            <option value="yes">Booklet requested</option>
            <option value="no">No booklet</option>
          </select>
          <select
            className="ui-select"
            value={gender}
            onChange={(event) => { setGender(event.target.value); setPage(1); }}
            aria-label="Filter by groom or bride"
          >
            <option value="all">All genders</option>
            <option value="groom">Groom</option>
            <option value="bride">Bride</option>
          </select>
          <select
            className="ui-select"
            value={melavaNumber}
            onChange={(event) => { setMelavaNumber(event.target.value); setPage(1); }}
            aria-label="Filter by Melava number"
          >
            <option value="all">All Melavas</option>
            {melavaNumbers.map((number) => (
              <option key={number} value={number}>{number}</option>
            ))}
          </select>
          <AgeRangeFilter
            from={ageInput.from}
            to={ageInput.to}
            onChange={(from, to) => { setAgeInput({ from, to }); setPage(1); }}
            onClear={() => { setAgeInput({ from: "", to: "" }); setPage(1); }}
            error={ageError}
            disabled={loading && !pagination}
          />
          {hasFilters ? (
            <button type="button" className="ui-reset" onClick={clearFilters}>Clear filters</button>
          ) : null}
        </div>

        {error ? (
          <div className="ui-alert" role="alert">
            <span>{error}</span>
            <button type="button" className="ui-btn small secondary" onClick={load} disabled={loading}>Try again</button>
          </div>
        ) : null}

        <div className="ui-card ui-table-card">
          <div className={`ui-table-scroll ${loading ? "is-loading" : ""}`}>
            <table className="ui-table">
              <thead>
                <tr>
                  <th>Candidate</th>
                  <th>Type</th>
                  <th>Melava Number</th>
                  <th>City</th>
                  <th>Registration Type</th>
                  <th>Registered On</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading && !registrations.length ? Array.from({ length: 6 }, (_, index) => (
                  <tr className="ui-skeleton-row" key={`skeleton-${index}`}>
                    <td colSpan="7"><span /></td>
                  </tr>
                )) : null}
                {!loading && !registrations.length ? (
                  <tr>
                    <td colSpan="7">
                      <div className="ui-state">
                        <Search size={30} />
                        <strong>No registrations found</strong>
                        <p>No Melava registrations match your current filters.</p>
                        {hasFilters ? <button type="button" className="ui-btn small" onClick={clearFilters}>Clear filters</button> : null}
                      </div>
                    </td>
                  </tr>
                ) : null}
                {registrations.map((row, index) => {
                  const name = row.fullName || row.profile?.fullName || "Unnamed candidate";
                  const profilePhoto = apiMediaUrl(getProfilePhoto(row, [
                    "profile_photo_url",
                    "photo_url",
                    "profile_photo",
                  ]));
                  return (
                    <tr key={row.id}>
                      <td>
                        <div className="ui-person">
                          {profilePhoto ? (
                            <span className="ui-avatar"><img src={profilePhoto} alt="" loading="lazy" /></span>
                          ) : (
                            <span className="ui-avatar">{initials(row.full_name)}</span>
                          )}
                          <span className="ui-person-text">
                            <strong>{name}</strong>
                            <small>{row.username || "Mobile unavailable"}</small>
                            <RecordEditorName record={row} />
                          </span>
                        </div>
                      </td>
                      <td>
                        <span className={`ui-badge ${String(row.groomOrBride).toLowerCase() === "bride" ? "brand" : "info"}`}>
                          {row.groomOrBride || "-"}
                        </span>
                      </td>
                      <td><span className="ui-chip">{row.melava_number || row.melava_title || "-"}</span></td>
                      <td>{row.profile?.professionalCity || row.city || "-"}</td>
                      <td>{row.profile?.registration_type === "existing" ? "Existing" : "Melava"}</td>
                      <td className="ui-muted">{formatDate(row.melava_registered_at || row.created_at)}</td>
                      <td>
                        <div className="ui-actions">
                          {canExportBiodata ? (
                            <button
                              type="button"
                              className="ui-btn small secondary"
                              onClick={() => showBiodata(row, (pagination?.from || 1) + index)}
                              disabled={!row.profile_id}
                              title={row.profile_id
                                ? "Choose a template and preview candidate BioData"
                                : "A candidate profile is not linked to this registration"}
                            >
                              <FileText size={14} /> BioData
                            </button>
                          ) : null}
                          {canApprovePayments ? (
                            <button type="button" className="ui-btn small" onClick={() => openPayment(row)}>
                              <Wallet size={14} /> Payment
                            </button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  );
                })}
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
              label="Melava registration pagination"
            />
          ) : null}
        </div>
      </div>

      {selectedRegistration && canApprovePayments ? (
        <div className="mp-modal" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget) closePayment();
        }}>
          <section className="mp-dialog mp-payment-dialog" role="dialog" aria-modal="true" aria-labelledby="mp-payment-title">
            <header className="mp-payment-header">
              <span className="mp-payment-header-icon"><Wallet size={22} /></span>
              <div>
                <h2 id="mp-payment-title">Add or update payment</h2>
                <p>{selectedRegistration.fullName || selectedRegistration.profile?.fullName || "Candidate"} · {selectedRegistration.username || "Mobile unavailable"}</p>
                <RecordEditorName record={selectedRegistration} />
              </div>
              <button type="button" className="mp-dialog-close" onClick={closePayment} disabled={paymentSaving} aria-label="Close payment dialog">
                <X size={18} />
              </button>
            </header>
            <form onSubmit={(event) => savePayment(event)}>
              <div className="mp-payment-form-body">
                <label className="mp-field">
                  <span>Payment category</span>
                  <select
                    value={paymentType}
                    onChange={(event) => {
                      const nextType = event.target.value;
                      setPaymentType(nextType);
                      setPaymentVerified(nextType === "booklet"
                        ? Boolean(selectedRegistration.booklet_payment_verified)
                        : nextType === "both"
                          ? Boolean(selectedRegistration.payment_verified && selectedRegistration.booklet_payment_verified)
                          : Boolean(selectedRegistration.payment_verified));
                      setPaymentFile(null);
                    }}
                    disabled={paymentSaving}
                  >
                    <option value="registration">Melava registration</option>
                    {selectedRegistration.is_booklet_req ? (
                      <option value="both">Melava registration + booklet</option>
                    ) : null}
                  </select>
                </label>
                <div className="mp-current-payment">
                  <span>Current payment proof</span>
                  {currentPaymentProofs.length ? currentPaymentProofs.map((proof) => (
                    <div className="mp-proof-item" key={proof.url}>
                      {currentPaymentProofs.length > 1 ? <small>{proof.label}</small> : null}
                      {/\.(?:jpe?g|png|webp)(?:[?#]|$)/i.test(proof.url) ? (
                        <img
                          className="mp-proof-preview"
                          src={apiMediaUrl(proof.url)}
                          alt={`${proof.label} payment proof`}
                        />
                      ) : (
                        <iframe
                          className="mp-proof-preview mp-proof-pdf"
                          src={apiMediaUrl(proof.url)}
                          title={`${proof.label} payment proof PDF`}
                        />
                      )}
                    </div>
                  )) : <span className="mp-muted">No proof uploaded</span>}
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
                {paymentError ? <div className="mp-alert er" role="alert">{paymentError}</div> : null}
              </div>
              <footer className="mp-payment-footer">
                <button type="button" className="mp-btn mp-btn-ghost" onClick={closePayment} disabled={paymentSaving}>Cancel</button>
                <button type="button" className="mp-btn mp-btn-red" onClick={() => savePayment(null, false)} disabled={paymentSaving}>
                  {paymentDecision === "reject" ? "Saving..." : "Reject"}
                </button>
                <button type="button" className="mp-btn mp-btn-green" onClick={() => savePayment(null, true)} disabled={paymentSaving}>
                  {paymentDecision === "verify" ? "Saving..." : "Verify"}
                </button>
              </footer>
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
