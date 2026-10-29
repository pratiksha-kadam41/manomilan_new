import { useCallback, useEffect, useRef, useState } from "react";
import {
  Search, Download, UserX, Pencil, CreditCard, Receipt, BadgeCheck,
  ChevronLeft, ChevronRight, ArrowUp, ArrowDown, ArrowUpDown, Phone,
  CheckCircle2, Clock, X, AlertTriangle, Users, RotateCcw, Archive,
} from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import { getSession, clearSession } from "../../../auth";
import "./profiles.css";

/* =====================================================================
   1. BACKEND SETTINGS  (the only part you need to adjust)
   ---------------------------------------------------------------------
   List API:  GET  {BASE}/profiles/allProfiles?q=&gender=&reg_type=&sort_by=&sort_order=&page=
   Expected JSON (Laravel paginator):
   {
     data: [{ id, registration_number, photo_url, full_name, mobile,
              registration_date, status, payment_verified }],
     current_page, last_page, per_page, total, from, to
   }
   Deactivate: PATCH {BASE}/profiles/deactivate/:id
   Export:     GET   {BASE}/profiles/export?q=&gender=&reg_type=
   ===================================================================== */
const BASE = import.meta.env.VITE_API_URL;

const LINKS = {
  inactive: "#/admin/inactive-profiles",
  view: (id) => `#/admin/profiles/${id}`,
  edit: (id) => `#/admin/profiles/edit/${id}`,
  payment: (id) => `#/admin/profiles/payment/${id}`,
  receipt: (id) => `${BASE}/profiles/generate-receipt/${id}`,
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
  { value: "melava_existing", label: "Melava 3 + Existing Registrations" },
];

const headers = () => {
  const s = getSession();
  return { Accept: "application/json", ...(s ? { Authorization: `Bearer ${s.token}` } : {}) };
};

// Sends the person back to login if the server says the session is invalid
const guard = (res) => {
  if (res.status === 401) {
    clearSession();
    window.location.hash = "#/login?expired=1";
    throw new Error("Session expired.");
  }
  if (!res.ok) throw new Error("Server error. Please try again.");
  return res;
};

/* ---- Sample data (used only while VITE_API_URL is not set) ----
   It reacts to search, filters, sorting and paging exactly like the real API should. */
const MALE = ["Rohan Patil", "Kunal Mehta", "Amit Kothari", "Vikram Gandhi", "Nikhil Oswal",
  "Sachin Shah", "Pranav Jain", "Harsh Doshi", "Yash Chopda", "Tejas Bafna"];
const FEMALE = ["Aarti Shah", "Neha Jain", "Pooja Shaha", "Sneha Doshi", "Riya Chopda",
  "Kajal Mehta", "Isha Patil", "Divya Gandhi", "Mansi Oswal", "Shruti Bafna"];
const TYPES = ["quick", "melava", "existing", "melava_existing"];

const MOCK = Array.from({ length: 45 }, (_, i) => ({
  id: 500 - i,
  registration_number: `MM3-${String(533 - i).padStart(4, "0")}`,
  photo_url: "",
  full_name: i % 2 ? FEMALE[(i >> 1) % 10] : MALE[(i >> 1) % 10],
  mobile: `98${String(10000000 + i * 111111).slice(0, 8)}`,
  registration_date: `${String(28 - (i % 20)).padStart(2, "0")}-10-2026`,
  status: i % 6 === 5 ? "Pending" : "Completed",
  payment_verified: i % 4 !== 3,
  gender: i % 2 ? "bride" : "groom",
  reg_type: TYPES[i % 4],
}));

const dateKey = (d) => d.split("-").reverse().join("");   // dd-mm-yyyy → yyyymmdd

function mockQuery({ q = "", gender, reg_type, sort_by, sort_order, page = 1 }) {
  const term = q.toLowerCase();
  let rows = MOCK.filter((p) =>
    (!term || [p.registration_number, p.full_name, p.mobile, p.registration_date, p.status]
      .some((v) => String(v).toLowerCase().includes(term))) &&
    (!gender || gender === "all" || p.gender === gender) &&
    (!reg_type || reg_type === "all" || p.reg_type === reg_type ||
      (reg_type === "melava_existing" && ["melava", "existing"].includes(p.reg_type))),
  );

  const key = (p) => (sort_by === "registration_number" ? p.registration_number : dateKey(p.registration_date));
  rows = [...rows].sort((a, b) => (key(a) < key(b) ? -1 : key(a) > key(b) ? 1 : 0) * (sort_order === "asc" ? 1 : -1));

  const per = 10;
  const total = rows.length;
  const last = Math.max(1, Math.ceil(total / per));
  const cur = Math.min(page, last);
  const data = rows.slice((cur - 1) * per, cur * per);
  return {
    data, current_page: cur, last_page: last, per_page: per, total,
    from: total ? (cur - 1) * per + 1 : 0, to: (cur - 1) * per + data.length,
  };
}

async function getProfiles(params) {
  // Leave out empty filters so the URL only carries what the person actually chose
  const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== "" && v != null));

  if (!BASE) {
    await new Promise((r) => setTimeout(r, 300));
    return mockQuery(clean);
  }
  const qs = new URLSearchParams(clean).toString();
  const res = guard(await fetch(`${BASE}/profiles/allProfiles?${qs}`, { headers: headers() }));
  return res.json();
}

async function deactivateProfile(id) {
  if (!BASE) return;
  guard(await fetch(`${BASE}/profiles/deactivate/${id}`, { method: "PATCH", headers: headers() }));
}

async function exportProfiles(params) {
  if (!BASE) throw new Error("Connect the API (VITE_API_URL) to export.");
  const qs = new URLSearchParams(params).toString();
  const res = guard(await fetch(`${BASE}/profiles/export?${qs}`, { headers: headers() }));
  const url = URL.createObjectURL(await res.blob());
  const a = Object.assign(document.createElement("a"), { href: url, download: "profiles.xlsx" });
  a.click();
  URL.revokeObjectURL(url);
}

/* ---------- Small helpers ---------- */
const initials = (name = "") =>
  name.split(" ").filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();

function pageList(current, last) {
  const set = new Set([1, 2, last - 1, last, current - 1, current, current + 1]);
  const nums = [...set].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b);
  const out = [];
  nums.forEach((n, i) => {
    if (i && n - nums[i - 1] > 1) out.push("…" + n);
    out.push(n);
  });
  return out;
}

function Avatar({ p, onOpen }) {
  const [broken, setBroken] = useState(false);
  if (!p.photo_url || broken)
    return <div className="pf-avatar pf-avatar-fallback">{initials(p.full_name)}</div>;
  return (
    <button type="button" className="pf-avatar" onClick={() => onOpen(p)} aria-label={`View photo of ${p.full_name}`}>
      <img src={p.photo_url} alt="" loading="lazy" onError={() => setBroken(true)} />
    </button>
  );
}

function SortHead({ label, field, sort, onSort }) {
  const active = sort.by === field;
  const Icon = !active ? ArrowUpDown : sort.order === "asc" ? ArrowUp : ArrowDown;
  return (
    <th>
      <button type="button" className={`pf-sort ${active ? "active" : ""}`} onClick={() => onSort(field)}>
        {label} <Icon size={14} />
      </button>
    </th>
  );
}

/* ====================================================================
   2. THE PAGE
   ==================================================================== */
export default function Profiles() {
  const [filters, setFilters] = useState({ q: "", gender: "", reg_type: "" });
  const [search, setSearch] = useState("");           // typed text (sent after a short pause)
  const [sort, setSort] = useState({ by: "registration_date", order: "desc" });
  const [page, setPage] = useState(1);

  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [photo, setPhoto] = useState(null);           // photo preview popup
  const [confirm, setConfirm] = useState(null);       // profile waiting for deactivate confirmation
  const [busy, setBusy] = useState(false);
  const [toast, setToast] = useState(null);

  const showToast = (type, text) => {
    setToast({ type, text });
    setTimeout(() => setToast(null), 3500);
  };

  // Wait 400 ms after typing stops, then search
  useEffect(() => {
    const t = setTimeout(() => { setFilters((f) => ({ ...f, q: search.trim() })); setPage(1); }, 400);
    return () => clearTimeout(t);
  }, [search]);

  const reqId = useRef(0);
  const load = useCallback(async () => {
    const id = ++reqId.current;                       // ignore out-of-date responses
    setLoading(true);
    setError("");
    try {
      const data = await getProfiles({ ...filters, sort_by: sort.by, sort_order: sort.order, page });
      if (id === reqId.current) setResult(data);
    } catch (e) {
      if (id === reqId.current) setError(e.message);
    } finally {
      if (id === reqId.current) setLoading(false);
    }
  }, [filters, sort, page]);

  useEffect(() => { load(); }, [load]);

  // Close popups with Esc
  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") { setPhoto(null); setConfirm(null); } };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const setFilter = (name) => (e) => { setFilters((f) => ({ ...f, [name]: e.target.value })); setPage(1); };
  const toggleSort = (field) => {
    setSort((s) => ({ by: field, order: s.by === field && s.order === "asc" ? "desc" : "asc" }));
    setPage(1);
  };
  const hasFilters = search || filters.gender || filters.reg_type;
  const clearFilters = () => { setSearch(""); setFilters({ q: "", gender: "", reg_type: "" }); setPage(1); };

  const handleExport = async () => {
    try { await exportProfiles(filters); } catch (e) { showToast("error", e.message); }
  };

  const handleDeactivate = async () => {
    setBusy(true);
    try {
      await deactivateProfile(confirm.id);
      showToast("success", `${confirm.full_name} has been deactivated.`);
      setConfirm(null);
      load();
    } catch (e) {
      showToast("error", e.message);
    } finally {
      setBusy(false);
    }
  };

  const rows = result?.data || [];

  return (
    <AdminLayout currentPath="/admin/profiles">
      <div className="pf-page">
      {/* ---------- Header ---------- */}
      <div className="pf-head">
        <div>
          <h1>Profile List</h1>
          <p>
            <Users size={15} /> {result ? `${result.total.toLocaleString()} profiles in total` : "Loading profiles…"}
          </p>
        </div>
        <div className="pf-head-actions">
          <a href={LINKS.inactive} className="pf-btn pf-btn-amber"><Archive size={16} /> Inactive List</a>
          <button type="button" className="pf-btn pf-btn-green" onClick={handleExport}>
            <Download size={16} /> Export
          </button>
        </div>
      </div>

      {/* ---------- Filters ---------- */}
      <div className="pf-card pf-filters">
        <div className="pf-search">
          <Search size={18} />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by Registration No, Name, Mobile, Date or Status"
            autoComplete="off"
          />
          {search && (
            <button type="button" onClick={() => setSearch("")} aria-label="Clear search"><X size={16} /></button>
          )}
        </div>

        <select value={filters.gender} onChange={setFilter("gender")} aria-label="Bride or Groom">
          <option value="" disabled>Select Bride/Groom</option>
          {GENDERS.map((g) => <option key={g.value} value={g.value}>{g.label}</option>)}
        </select>

        <select value={filters.reg_type} onChange={setFilter("reg_type")} aria-label="Registration type">
          <option value="" disabled>Select Registration Type</option>
          {REG_TYPES.map((g) => <option key={g.value} value={g.value}>{g.label}</option>)}
        </select>

        {hasFilters && (
          <button type="button" className="pf-reset" onClick={clearFilters}>
            <RotateCcw size={15} /> Reset
          </button>
        )}
      </div>

      {/* ---------- Table ---------- */}
      <div className="pf-card pf-table-card">
        {error && (
          <div className="pf-state pf-state-error">
            <AlertTriangle size={34} />
            <p>{error}</p>
            <button type="button" className="pf-btn pf-btn-teal" onClick={load}>Try again</button>
          </div>
        )}

        {!error && (
          <div className={`pf-scroll ${loading ? "is-loading" : ""}`}>
            <table className="pf-table">
              <thead>
                <tr>
                  <th>No.</th>
                  <SortHead label="Registration No." field="registration_number" sort={sort} onSort={toggleSort} />
                  <th>Photo</th>
                  <th>Full Name</th>
                  <th>Mobile</th>
                  <SortHead label="Registered On" field="registration_date" sort={sort} onSort={toggleSort} />
                  <th>Status</th>
                  <th className="pf-th-actions">Action</th>
                </tr>
              </thead>
              <tbody>
                {loading && !result &&
                  Array.from({ length: 6 }, (_, i) => (
                    <tr key={i} className="pf-skel">
                      <td colSpan={8}><span /></td>
                    </tr>
                  ))}

                {rows.map((p, i) => {
                  const done = String(p.status).toLowerCase() === "completed";
                  return (
                    <tr key={p.id}>
                      <td className="pf-muted">{(result.from ?? 1) + i}</td>
                      <td><span className="pf-regno">{p.registration_number}</span></td>
                      <td><Avatar p={p} onOpen={setPhoto} /></td>
                      <td><a className="pf-name" href={LINKS.view(p.id)}>{p.full_name}</a></td>
                      <td><span className="pf-mobile"><Phone size={13} /> {p.mobile}</span></td>
                      <td className="pf-muted">{p.registration_date}</td>
                      <td>
                        <span className={`pf-pill ${done ? "ok" : "wait"}`}>
                          {done ? <CheckCircle2 size={14} /> : <Clock size={14} />} {p.status}
                        </span>
                      </td>
                      <td>
                        <div className="pf-actions">
                          <a href={LINKS.edit(p.id)} className="pf-act edit" title="Edit" aria-label="Edit"><Pencil size={15} /><span className="pf-lbl">Edit</span></a>

                          <a href={LINKS.payment(p.id)} className="pf-act pay" title="Payment" aria-label="Payment">
                            <CreditCard size={15} /><span className="pf-lbl">Payment</span>
                            {p.payment_verified && (
                              <span className="pf-verified" title="Payment verified"><BadgeCheck size={16} /></span>
                            )}
                          </a>

                          {p.payment_verified ? (
                            <a href={LINKS.receipt(p.id)} target="_blank" rel="noopener noreferrer" className="pf-act receipt" title="Receipt" aria-label="Receipt">
                              <Receipt size={15} /><span className="pf-lbl">Receipt</span>
                            </a>
                          ) : (
                            <button
                              type="button"
                              className="pf-act receipt disabled"
                              onClick={() => showToast("error", "Please verify the payment first.")}
                              title="Verify the payment first"
                            >
                              <Receipt size={15} /><span className="pf-lbl">Receipt</span>
                            </button>
                          )}

                          <button type="button" className="pf-act danger" onClick={() => setConfirm(p)} title="Deactivate" aria-label="Deactivate">
                            <UserX size={15} /><span className="pf-lbl">Deactivate</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>

            {!loading && rows.length === 0 && (
              <div className="pf-state">
                <Search size={34} />
                <p>No profiles match your search.</p>
                {hasFilters && <button type="button" className="pf-btn pf-btn-teal" onClick={clearFilters}>Clear filters</button>}
              </div>
            )}
          </div>
        )}

        {/* ---------- Pagination ---------- */}
        {result && result.last_page > 0 && (
          <div className="pf-foot">
            <span className="pf-muted">
              Showing {result.from ?? 0}–{result.to ?? 0} of {result.total.toLocaleString()}
            </span>
            <nav className="pf-pages" aria-label="Pagination">
              <button type="button" disabled={page <= 1} onClick={() => setPage(page - 1)} aria-label="Previous page">
                <ChevronLeft size={16} />
              </button>
              {pageList(page, result.last_page).map((n) =>
                typeof n === "string" ? (
                  <span key={n} className="gap">…</span>
                ) : (
                  <button key={n} type="button" className={n === page ? "active" : ""} onClick={() => setPage(n)}>
                    {n}
                  </button>
                ),
              )}
              <button type="button" disabled={page >= result.last_page} onClick={() => setPage(page + 1)} aria-label="Next page">
                <ChevronRight size={16} />
              </button>
            </nav>
          </div>
        )}
      </div>

      {/* ---------- Photo preview ---------- */}
      {photo && (
        <div className="pf-modal" onClick={() => setPhoto(null)}>
          <div className="pf-lightbox" onClick={(e) => e.stopPropagation()}>
            <button type="button" className="pf-close" onClick={() => setPhoto(null)} aria-label="Close"><X size={20} /></button>
            <img src={photo.photo_url} alt={photo.full_name} />
            <p>{photo.full_name} · {photo.registration_number}</p>
          </div>
        </div>
      )}

      {/* ---------- Deactivate confirmation ---------- */}
      {confirm && (
        <div className="pf-modal" onClick={() => !busy && setConfirm(null)}>
          <div className="pf-dialog" role="alertdialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
            <div className="pf-dialog-icon"><AlertTriangle size={26} /></div>
            <h3>Deactivate this profile?</h3>
            <p><strong>{confirm.full_name}</strong> ({confirm.registration_number}) will be moved to the Inactive List.</p>
            <div className="pf-dialog-btns">
              <button type="button" className="pf-btn pf-btn-ghost" disabled={busy} onClick={() => setConfirm(null)}>Cancel</button>
              <button type="button" className="pf-btn pf-btn-red" disabled={busy} onClick={handleDeactivate}>
                {busy ? "Deactivating…" : "Yes, deactivate"}
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
