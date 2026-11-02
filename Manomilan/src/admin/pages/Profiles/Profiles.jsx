import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import {
  Search, Download, UserX, Pencil, CreditCard, Receipt, BadgeCheck,
  ArrowUp, ArrowDown, ArrowUpDown, Phone, CheckCircle2, Clock, X,
  AlertTriangle, Users, RotateCcw, Archive, UserCheck, ShieldAlert,
} from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import AdminStats from "../../components/AdminStats";
import AdminPagination from "../../components/AdminPagination";
import AgeRangeFilter from "../../components/AgeRangeFilter";
import useDebouncedValue from "../../hooks/useDebouncedValue";
import { request, download } from "../../../api";
import { touchSession } from "../../../auth";
import { PermissionGate, useAdminPermission } from "../../../permission-context";
import "../../components/admin-ui.css";
import "./profiles.css";

const LINKS = {
  inactive: "/admin/inactive-profiles",
  view: (id, rowNumber) => `/admin/profiles/${id}/details?sr_no=${rowNumber}`,
  edit: (id) => `/admin/profiles/edit/${id}`,
  payment: (id) => `/admin/profiles/payment/${id}`,
  receipt: (id) => `/admin/profiles/receipt/${id}`,
};

const GENDERS = [
  { value: "all", label: "All Profiles" },
  { value: "bride", label: "Bride" },
  { value: "groom", label: "Groom" },
];

const REG_TYPES = [
  { value: "all", label: "All Types" },
  { value: "quick", label: "Quick Registration" },
  { value: "melava", label: "Melava Registration" },
  { value: "existing", label: "Existing Registration" },
  { value: "melava_existing", label: "Melava + Existing Registrations" },
];

const PROFILE_VIEWS = {
  "/admin/profiles": { title: "Profile List", description: "Browse and manage registered profiles.", totalLabel: "Total Profiles" },
  "/admin/nri-profiles": { title: "NRI Profiles", description: "Profiles matching the existing NRI location criteria.", totalLabel: "Total NRI Profiles" },
  "/admin/verified-profiles": { title: "Verified Profiles", description: "Profiles with completed identity and birth-date verification.", totalLabel: "Total Verified" },
  "/admin/unverified-profiles": { title: "Unverified Profiles", description: "Profiles still waiting for verification.", totalLabel: "Total Unverified" },
  "/admin/inactive-profiles": { title: "Inactive Profiles", description: "Profiles deactivated by administrators.", totalLabel: "Total Inactive" },
};

async function getProfiles(params) {
  const clean = Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== "" && v != null),
  );
  const qs = new URLSearchParams(clean).toString();
  return request(`/profiles/allProfiles${qs ? `?${qs}` : ""}`);
}

async function deactivateProfile(id) {
  return request(`/profiles/deactivate/${id}`, { method: "PATCH" });
}

function exportFallbackName() {
  const date = new Date();
  const pad = (value) => String(value).padStart(2, "0");
  const timestamp = [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
  ].join("-") + `_${pad(date.getHours())}-${pad(date.getMinutes())}-${pad(date.getSeconds())}`;

  return `profiles_export_${timestamp}.csv`;
}

async function exportProfiles(params) {
  const clean = Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== "" && v != null),
  );
  const qs = new URLSearchParams(clean).toString();
  await download(`/profiles/export${qs ? `?${qs}` : ""}`, exportFallbackName());
}

const initials = (name = "") =>
  name.split(" ").filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();

function Avatar({ p, onOpen }) {
  const [broken, setBroken] = useState(false);
  if (!p.photo_url || broken)
    return <div className="ui-avatar" aria-hidden="true">{initials(p.full_name)}</div>;
  return (
    <button type="button" className="ui-avatar ui-avatar-btn" onClick={() => onOpen(p)} aria-label={`View photo of ${p.full_name}`}>
      <img src={p.photo_url} alt="" loading="lazy" onError={() => setBroken(true)} />
    </button>
  );
}

function SortHead({ label, field, sort, onSort }) {
  const active = sort.by === field;
  const Icon = !active ? ArrowUpDown : sort.order === "asc" ? ArrowUp : ArrowDown;
  return (
    <th>
      <button type="button" className={`ui-sort ${active ? "active" : ""}`} onClick={() => onSort(field)}>
        {label} <Icon size={13} />
      </button>
    </th>
  );
}

export default function Profiles() {
  const { pathname } = useLocation();
  const currentPath = pathname.replace(/\/+$/, "") || "/";
  const view = PROFILE_VIEWS[currentPath] || PROFILE_VIEWS["/admin/profiles"];
  const canEditProfiles = useAdminPermission("edit", currentPath);
  const canDeactivateProfiles = useAdminPermission("edit", "/admin/verified-profiles");
  const verificationStatus =
    currentPath === "/admin/verified-profiles"
      ? "verified"
      : currentPath === "/admin/unverified-profiles"
        ? "unverified"
        : undefined;
  const isNriView = currentPath === "/admin/nri-profiles";
  const isInactiveView = currentPath === "/admin/inactive-profiles";

  const [filters, setFilters] = useState({ gender: "all", reg_type: "all" });
  const [search, setSearch] = useState("");
  const [ageInput, setAgeInput] = useState({ from: "", to: "" });
  const [sort, setSort] = useState({ by: "registration_date", order: "desc" });
  const [page, setPage] = useState(1);

  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [photo, setPhoto] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [busy, setBusy] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [toast, setToast] = useState(null);
  const toastTimer = useRef(null);

  const showToast = (type, text) => {
    setToast({ type, text });
    window.clearTimeout(toastTimer.current);
    toastTimer.current = window.setTimeout(() => setToast(null), 3500);
  };

  const openReceipt = (event, id) => {
    event.preventDefault();
    touchSession();
    const storedSession = sessionStorage.getItem("auth");

    if (!storedSession) {
      showToast("error", "Your session has expired. Please sign in again.");
      return;
    }

    let receiptTab;
    try {
      const session = JSON.parse(storedSession);
      if (!session?.token || !["admin", "super"].includes(session.role)) {
        showToast("error", "Administrator access is required to open receipts.");
        return;
      }

      receiptTab = window.open("about:blank", "_blank");
      if (!receiptTab) {
        showToast("error", "Allow pop-ups to open the receipt in a new tab.");
        return;
      }

      receiptTab.sessionStorage.setItem("auth", storedSession);
      receiptTab.opener = null;
      receiptTab.location.replace(new URL(LINKS.receipt(id), window.location.origin).href);
    } catch (error) {
      receiptTab?.close();
      showToast("error", "Could not open the receipt. Please try again.");
      console.error("Could not open receipt tab:", error);
    }
  };

  const debouncedSearch = useDebouncedValue(search, 350);
  const debouncedAge = useDebouncedValue(ageInput, 400);
  const activeQuery = debouncedSearch.trim();
  const ageFrom = debouncedAge.from;
  const ageTo = debouncedAge.to;
  const ageError = ageFrom !== "" && ageTo !== "" && Number(ageFrom) > Number(ageTo)
    ? "Minimum age cannot be greater than maximum age."
    : "";

  const reqId = useRef(0);
  const load = useCallback(async () => {
    const id = ++reqId.current;
    if (ageError) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const data = await getProfiles({
        ...filters,
        q: activeQuery,
        age_from: ageFrom || undefined,
        age_to: ageTo || undefined,
        verification_status: verificationStatus,
        nri: isNriView ? "1" : undefined,
        inactive: isInactiveView ? "1" : undefined,
        sort_by: sort.by,
        sort_order: sort.order,
        page,
      });
      if (id === reqId.current) setResult(data);
    } catch (e) {
      if (id === reqId.current) setError(e.message);
    } finally {
      if (id === reqId.current) setLoading(false);
    }
  }, [filters, activeQuery, ageFrom, ageTo, ageError, sort, page, verificationStatus, isNriView, isInactiveView]);

  useEffect(() => {
    const timer = setTimeout(load, 0);
    return () => clearTimeout(timer);
  }, [load]);

  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") { setPhoto(null); setConfirm(null); } };
    window.addEventListener("keydown", onKey);
    return () => {
      window.removeEventListener("keydown", onKey);
      window.clearTimeout(toastTimer.current);
    };
  }, []);

  const setFilter = (name) => (e) => { setFilters((f) => ({ ...f, [name]: e.target.value })); setPage(1); };
  const toggleSort = (field) => {
    setSort((s) => ({ by: field, order: s.by === field && s.order === "asc" ? "desc" : "asc" }));
    setPage(1);
  };
  const hasFilters = Boolean(
    activeQuery ||
    (filters.gender && filters.gender !== "all") ||
    (filters.reg_type && filters.reg_type !== "all") ||
    ageFrom ||
    ageTo,
  );
  const clearFilters = () => {
    setSearch("");
    setAgeInput({ from: "", to: "" });
    setFilters({ gender: "all", reg_type: "all" });
    setPage(1);
  };

  const handleExport = async () => {
    if (ageError) {
      showToast("error", ageError);
      return;
    }
    setExporting(true);
    try {
      await exportProfiles({
        ...filters,
        q: activeQuery,
        age_from: ageFrom || undefined,
        age_to: ageTo || undefined,
        verification_status: verificationStatus,
        nri: isNriView ? "1" : undefined,
        sort_by: sort.by,
        sort_order: sort.order,
      });
      showToast("success", "Profiles exported successfully.");
    } catch (e) {
      showToast("error", e.message);
    } finally {
      setExporting(false);
    }
  };

  const handleDeactivate = async () => {
    setBusy(true);
    try {
      await request(`/profiles/${isInactiveView ? "activate" : "deactivate"}/${confirm.id}`, { method: "PATCH" });
      showToast("success", `${confirm.full_name} has been ${isInactiveView ? "activated" : "deactivated"}.`);
      setConfirm(null);
      load();
    } catch (e) {
      showToast("error", e.message);
    } finally {
      setBusy(false);
    }
  };

  const rows = result?.data || [];
  const stats = result?.stats || {};
  const statsLoading = loading && !result;

  const statItems = useMemo(() => [
    { key: "total", label: view.totalLabel, value: stats.total, icon: Users, tone: "teal", hint: "Matching filters" },
    { key: "active", label: "Active", value: stats.active, icon: UserCheck, tone: "green", hint: "Currently active" },
    { key: "verified", label: "Verified", value: stats.verified, icon: BadgeCheck, tone: "blue", hint: "Verification completed" },
    { key: "inactive", label: "Inactive", value: stats.inactive, icon: ShieldAlert, tone: "amber", hint: "Moved to inactive list" },
  ], [stats.total, stats.active, stats.verified, stats.inactive, view.totalLabel]);

  return (
    <AdminLayout currentPath={currentPath}>
      <div className="adm-ui">
        <div className="ui-head">
          <div>
            <h1>{view.title}</h1>
            <p>
              <Users size={15} /> {result
                ? `${Number(stats.total ?? result.total ?? 0).toLocaleString("en-IN")} ${currentPath === "/admin/profiles" ? "profiles in total" : "profiles found"}`
                : view.description}
            </p>
          </div>
          <div className="ui-head-actions">
            <a href={LINKS.inactive} className="ui-btn secondary"><Archive size={16} /> Inactive List</a>
            <PermissionGate permission="export" currentPath={currentPath}>
              <button type="button" className="ui-btn" onClick={handleExport} disabled={exporting}>
                <Download size={16} /> {exporting ? "Exporting…" : "Export"}
              </button>
            </PermissionGate>
          </div>
        </div>

        <AdminStats items={statItems} loading={statsLoading} label={`${view.title} statistics`} />

        <div className="ui-card ui-toolbar">
          <label className="ui-search">
            <Search size={17} />
            <input
              type="search"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              placeholder="Search by Registration No, Name, Mobile, Date or Status"
              autoComplete="off"
              aria-label="Search profiles"
            />
            {search && (
              <button type="button" onClick={() => { setSearch(""); setPage(1); }} aria-label="Clear search"><X size={16} /></button>
            )}
          </label>

          <select className="ui-select" value={filters.gender} onChange={setFilter("gender")} aria-label="Filter by profile type">
            {GENDERS.map((g) => <option key={g.value} value={g.value}>{g.label}</option>)}
          </select>

          <select className="ui-select" value={filters.reg_type} onChange={setFilter("reg_type")} aria-label="Filter by registration type">
            {REG_TYPES.map((g) => <option key={g.value} value={g.value}>{g.label}</option>)}
          </select>

          <AgeRangeFilter
            from={ageInput.from}
            to={ageInput.to}
            onChange={(from, to) => { setAgeInput({ from, to }); setPage(1); }}
            onClear={() => { setAgeInput({ from: "", to: "" }); setPage(1); }}
            error={ageError}
            disabled={loading && !result}
          />

          {hasFilters && (
            <button type="button" className="ui-reset" onClick={clearFilters}>
              <RotateCcw size={15} /> Reset
            </button>
          )}
        </div>

        <div className="ui-card ui-table-card">
          {error && (
            <div className="ui-state error">
              <AlertTriangle size={32} />
              <strong>Could not load profiles</strong>
              <p>{error}</p>
              <button type="button" className="ui-btn small" onClick={load}>Try again</button>
            </div>
          )}

          {!error && (
            <div className={`ui-table-scroll ${loading ? "is-loading" : ""}`}>
              <table className="ui-table">
                <thead>
                  <tr>
                    <th>No.</th>
                    <SortHead label="Registration No." field="registration_number" sort={sort} onSort={toggleSort} />
                    <th>Photo</th>
                    <th>Full Name</th>
                    <th>Mobile</th>
                    <SortHead label="Registered On" field="registration_date" sort={sort} onSort={toggleSort} />
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {loading && !result &&
                    Array.from({ length: 6 }, (_, i) => (
                      <tr key={i} className="ui-skeleton-row">
                        <td colSpan={8}><span /></td>
                      </tr>
                    ))}

                  {rows.map((p, i) => {
                    const done = p.is_verified === true
                      || ["completed", "complete", "verified", "approved", "approve"].includes(String(p.status).toLowerCase());
                    const rowNumber = (result.from ?? 1) + i;
                    return (
                      <tr key={p.id}>
                        <td className="ui-muted">{rowNumber}</td>
                        <td><span className="ui-chip">{p.registration_number}</span></td>
                        <td><Avatar p={p} onOpen={setPhoto} /></td>
                        <td><Link className="ui-link" to={LINKS.view(p.id, rowNumber)}>{p.full_name}</Link></td>
                        <td><span className="ui-muted" style={{ display: "inline-flex", alignItems: "center", gap: 6 }}><Phone size={13} /> {p.mobile}</span></td>
                        <td className="ui-muted">{p.registration_date}</td>
                        <td>
                          <span className={`ui-badge ${done ? "ok" : "wait"}`}>
                            {done ? <CheckCircle2 size={13} /> : <Clock size={13} />} {p.status}
                          </span>
                        </td>
                        <td>
                          <div className="ui-actions">
                            {canEditProfiles ? <a href={LINKS.edit(p.id)} className="ui-icon-btn amber" title="Edit" aria-label="Edit"><Pencil size={15} /></a> : null}

                            <a href={LINKS.payment(p.id)} className="ui-icon-btn teal" title="Payment" aria-label="Payment">
                              <CreditCard size={15} />
                            </a>

                            {p.payment_verified ? (
                              <a href={LINKS.receipt(p.id)} onClick={(event) => openReceipt(event, p.id)} className="ui-icon-btn green" title="Receipt" aria-label="Receipt">
                                <Receipt size={15} />
                              </a>
                            ) : (
                              <button
                                type="button"
                                className="ui-icon-btn"
                                onClick={() => showToast("error", "Please verify the payment first.")}
                                title="Verify the payment first"
                                aria-label="Receipt unavailable"
                              >
                                <Receipt size={15} />
                              </button>
                            )}

                            {canDeactivateProfiles ? <button type="button" className={`ui-icon-btn ${isInactiveView ? "green" : "red"}`} onClick={() => setConfirm(p)} title={isInactiveView ? "Activate" : "Deactivate"} aria-label={isInactiveView ? "Activate" : "Deactivate"}>
                              {isInactiveView ? <UserCheck size={15} /> : <UserX size={15} />}
                            </button> : null}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>

              {!loading && rows.length === 0 && (
                <div className="ui-state">
                  <Search size={32} />
                  <strong>No profiles found</strong>
                  <p>No profiles match your current search and filters.</p>
                  {hasFilters && <button type="button" className="ui-btn small" onClick={clearFilters}>Clear filters</button>}
                </div>
              )}
            </div>
          )}

          {result && (
            <AdminPagination
              page={result.current_page || page}
              lastPage={result.last_page || 1}
              from={result.from}
              to={result.to}
              total={stats.total ?? result.total}
              onPage={setPage}
              disabled={loading}
              label="Profile pagination"
            />
          )}
        </div>

        {photo && (
          <div className="pf-modal" onClick={() => setPhoto(null)}>
            <div className="pf-lightbox" onClick={(e) => e.stopPropagation()}>
              <button type="button" className="pf-close" onClick={() => setPhoto(null)} aria-label="Close"><X size={20} /></button>
              <img src={photo.photo_url} alt={photo.full_name} />
              <p>{photo.full_name} · {photo.registration_number}</p>
            </div>
          </div>
        )}

        {confirm && canDeactivateProfiles && (
          <div className="pf-modal" onClick={() => !busy && setConfirm(null)}>
            <div className="pf-dialog" role="alertdialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
              <div className="pf-dialog-icon"><AlertTriangle size={26} /></div>
              <h3>{isInactiveView ? "Activate" : "Deactivate"} this profile?</h3>
              <p><strong>{confirm.full_name}</strong> ({confirm.registration_number}) will be {isInactiveView ? "moved to the active list." : "moved to the Inactive List."}</p>
              <div className="pf-dialog-btns">
                <button type="button" className="pf-btn pf-btn-ghost" disabled={busy} onClick={() => setConfirm(null)}>Cancel</button>
                <button type="button" className="pf-btn pf-btn-red" disabled={busy} onClick={handleDeactivate}>
                  {busy ? (isInactiveView ? "Activating…" : "Deactivating…") : `Yes, ${isInactiveView ? "activate" : "deactivate"}`}
                </button>
              </div>
            </div>
          </div>
        )}

        {toast && <div className={`pf-toast ${toast.type}`} role="status">{toast.text}</div>}
      </div>
    </AdminLayout>
  );
}
