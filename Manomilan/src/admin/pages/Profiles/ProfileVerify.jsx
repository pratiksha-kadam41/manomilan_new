import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  Contact, CalendarDays, MapPin, GraduationCap, Briefcase, IndianRupee, Award, HeartPulse, Camera,
  Eye, ArrowLeft, Save, Loader2, AlertTriangle, CheckCircle2, Clock, XCircle, Ban,
} from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import { getSession, clearSession } from "../../../auth";
import "./verify.css";

/* =====================================================================
   1. THE DOCUMENT LIST  (add / rename / reorder documents here only)
   key       → the name the backend uses. Fields sent to the server are
               "<key>_status" and "<key>_remarks" (same as your old Laravel form)
   required  → shows the red * after the name
   tone      → colour of the icon tile (see verify.css)
   ===================================================================== */
const DOCUMENTS = [
  { key: "identificationProof",       label: "Identification Proof", required: true, suggested: "Aadhaar / Passport",   icon: Contact,       tone: "blue" },
  { key: "birthDateProof",            label: "Birth Date Proof",     required: true, suggested: "PAN Card / Passport",  icon: CalendarDays,  tone: "red" },
  { key: "addressProof",              label: "Address Proof",        suggested: "Aadhaar / Passport",   icon: MapPin,        tone: "yellow" },
  { key: "educationProof",            label: "Education Proof",      suggested: "Marksheet / Degree",   icon: GraduationCap, tone: "purple" },
  { key: "professionProof",           label: "Profession Proof",     suggested: "Company Letter",       icon: Briefcase,     tone: "slate" },
  { key: "incomeProof",               label: "Income Proof",         suggested: "Salary Slip / ITR",    icon: IndianRupee,   tone: "green" },
  { key: "casteCertificate",          label: "Caste Proof",          suggested: "Caste Certificate",    icon: Award,         tone: "orange" },
  { key: "medicalFitnessCertificate", label: "Medical Certificate",  suggested: "Doctor's Certificate", icon: HeartPulse,    tone: "teal" },
  { key: "selfieVerification",        label: "Selfie Verification",  suggested: "Live Selfie",          icon: Camera,        tone: "brown" },
];

// "value" is what the backend stores; "label" is what the admin sees
const STATUSES = [
  { value: "pending",      label: "Pending",      icon: Clock },
  { value: "verified",     label: "Verified",     icon: CheckCircle2 },
  { value: "rejected",     label: "Rejected",     icon: XCircle },
  { value: "not_eligible", label: "Not Eligible", icon: Ban },
];

/* =====================================================================
   2. BACKEND SETTINGS  (the only part you need to adjust)
   Load:  GET  {BASE}/profiles/:id/verification
          → {
              profile:   { id, full_name, registration_number, registration_date, photo_url },
              documents: { identificationProof: { file_url, status, remarks }, birthDateProof: {...}, ... }
            }
            (file_url is empty/missing when nothing was uploaded; status is one of the STATUSES values)
   Save:  POST {BASE}/profiles/:id/verify
          body (JSON): { identificationProof_status: "verified", identificationProof_remarks: "", ... }
   ===================================================================== */
const BASE = import.meta.env.VITE_API_URL;
const LINKS = { back: "#/admin/profiles" };
const MAX_REMARKS = 255;                    // keep in step with your database column / Laravel validation

const authHeaders = () => {
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
  if (res.status === 404) throw new Error("This profile was not found.");
  if (res.status === 422) throw new Error("Some values were not accepted. Please check and try again.");
  if (!res.ok) throw new Error("Server error. Please try again.");
  return res;
};

/* ---- Security helpers ---- */
// Only whole numbers are accepted as a profile id (blocks "../" tricks and odd characters)
const isValidId = (id) => /^\d{1,12}$/.test(String(id ?? ""));

// A document link is only used if it is http(s) or a site-relative path.
// This stops "javascript:" or "data:" links from ever reaching an <a href>.
const safeUrl = (url) => {
  if (!url || typeof url !== "string") return "";
  try {
    const u = new URL(url, window.location.origin);
    return u.protocol === "https:" || u.protocol === "http:" ? u.href : "";
  } catch {
    return "";
  }
};

// Only status values the UI knows about are accepted from the server
const cleanStatus = (s) => (STATUSES.some((x) => x.value === s) ? s : "pending");

/* ---- Sample data so you can see the UI before the API is connected (development only) ---- */
const MOCK = {
  profile: {
    id: 396, full_name: "Aarti Shah", registration_number: "MM3-0533",
    registration_date: "15 May, 2026", photo_url: "",
  },
  documents: {
    identificationProof: { file_url: "/favicon.svg", status: "verified", remarks: "" },
    birthDateProof:      { file_url: "/favicon.svg", status: "verified", remarks: "" },
  },
};

async function getVerification(id) {
  if (!BASE) {
    if (!import.meta.env.DEV) throw new Error("The server address is not configured.");
    await new Promise((r) => setTimeout(r, 400));
    return MOCK;
  }
  const res = guard(await fetch(`${BASE}/profiles/${encodeURIComponent(id)}/verification`, { headers: authHeaders() }));
  return res.json();
}

async function saveVerification(id, form) {
  // turns { identificationProof: {status, remarks} } into { identificationProof_status, identificationProof_remarks }
  const body = {};
  DOCUMENTS.forEach(({ key }) => {
    body[`${key}_status`] = form[key].status;
    body[`${key}_remarks`] = form[key].remarks.trim().slice(0, MAX_REMARKS);
  });

  if (!BASE) {
    await new Promise((r) => setTimeout(r, 500));
    return;                                           // sample mode: pretend it saved
  }
  guard(await fetch(`${BASE}/profiles/${encodeURIComponent(id)}/verify`, {
    method: "POST",
    headers: { ...authHeaders(), "Content-Type": "application/json" },
    body: JSON.stringify(body),
  }));
}

/* ---------- Small helpers ---------- */
const initials = (name = "") =>
  name.split(" ").filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();

function Photo({ profile }) {
  const [broken, setBroken] = useState(false);
  if (!profile.photo_url || broken)
    return <div className="pv-photo pv-photo-fallback">{initials(profile.full_name)}</div>;
  return (
    <div className="pv-photo">
      <img src={profile.photo_url} alt={profile.full_name} onError={() => setBroken(true)} />
    </div>
  );
}

/* ====================================================================
   3. THE PAGE
   ==================================================================== */
export default function ProfileVerify({ params }) {
  const id = params?.id;
  const idOk = isValidId(id);

  const [profile, setProfile] = useState(null);
  const [files, setFiles] = useState({});             // key → uploaded file link
  const [form, setForm] = useState({});               // key → { status, remarks }
  const [savedSnapshot, setSavedSnapshot] = useState("");

  const [loading, setLoading] = useState(idOk);
  const [loadError, setError] = useState("");
  const error = idOk ? loadError : "This profile link is not valid.";
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);

  const toastTimer = useRef(null);
  const showToast = (type, text) => {
    setToast({ type, text });
    clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(null), 3500);
  };
  useEffect(() => () => clearTimeout(toastTimer.current), []);

  const load = useCallback(async () => {
    if (!idOk) return;
    try {
      const data = await getVerification(id);
      const docs = data.documents || {};
      const next = {};
      DOCUMENTS.forEach(({ key }) => {
        next[key] = { status: cleanStatus(docs[key]?.status), remarks: String(docs[key]?.remarks ?? "").slice(0, MAX_REMARKS) };
      });
      setProfile({ ...data.profile, photo_url: safeUrl(data.profile?.photo_url) });
      setFiles(Object.fromEntries(DOCUMENTS.map(({ key }) => [key, safeUrl(docs[key]?.file_url)])));
      setForm(next);
      setSavedSnapshot(JSON.stringify(next));
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [id, idOk]);

  useEffect(() => { load(); }, [load]);

  const setField = (key, field, value) =>
    setForm((f) => ({ ...f, [key]: { ...f[key], [field]: value } }));

  const dirty = !loading && JSON.stringify(form) !== savedSnapshot;

  const counts = useMemo(() => {
    const c = { pending: 0, verified: 0, rejected: 0, not_eligible: 0 };
    Object.values(form).forEach((v) => { c[v.status] = (c[v.status] || 0) + 1; });
    return c;
  }, [form]);

  // Warn before closing / refreshing the tab with unsaved changes
  useEffect(() => {
    if (!dirty) return;
    const warn = (e) => { e.preventDefault(); e.returnValue = ""; };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);

  const handleSave = async (e) => {
    e.preventDefault();
    if (saving || !dirty) return;               // blocks double-clicks and empty saves
    setSaving(true);
    try {
      await saveVerification(id, form);
      setSavedSnapshot(JSON.stringify(form));
      showToast("success", "Verification saved successfully.");
    } catch (err) {
      showToast("error", err.message);
    } finally {
      setSaving(false);
    }
  };

  const total = DOCUMENTS.length;
  const percent = Math.round((counts.verified / total) * 100);

  return (
    <AdminLayout currentPath="/admin/profiles">
      <div className="pv-page">
        {/* ---------- Profile summary ---------- */}
        <section className="pv-card pv-profile">
          {profile ? (
            <>
              <Photo profile={profile} />
              <div className="pv-who">
                <h1>{profile.full_name}</h1>
                <p>
                  <span className="pv-regno">{profile.registration_number}</span>
                  <span className="pv-dot" /> Registered on {profile.registration_date}
                </p>
              </div>
              <div className="pv-progress" title={`${counts.verified} of ${total} documents verified`}>
                <div className="pv-progress-text">
                  <strong>{counts.verified}</strong> of {total} verified
                </div>
                <div className="pv-bar"><span style={{ width: `${percent}%` }} /></div>
              </div>
            </>
          ) : (
            <div className="pv-profile-skel"><span /></div>
          )}
        </section>

        {/* ---------- Verification panel ---------- */}
        <form className="pv-card pv-panel" onSubmit={handleSave}>
          <div className="pv-panel-head">
            <h2>Document Verification Panel</h2>
            <div className="pv-chips">
              {STATUSES.map((s) => (
                <span key={s.value} className={`pv-chip ${s.value}`}>
                  <s.icon size={13} /> {counts[s.value] || 0} {s.label}
                </span>
              ))}
              {dirty && <span className="pv-dirty">● Unsaved changes</span>}
            </div>
          </div>

          {error ? (
            <div className="pv-state">
              <AlertTriangle size={34} />
              <p>{error}</p>
              <button type="button" className="pv-btn pv-btn-teal" onClick={() => { setError(""); setLoading(true); load(); }}>Try again</button>
            </div>
          ) : (
            <div className="pv-scroll">
              <div className="pv-row pv-row-head" role="presentation">
                <span style={{ gridArea: "doc" }}>Document</span>
                <span style={{ gridArea: "view" }}>View</span>
                <span style={{ gridArea: "status" }}>Status</span>
                <span style={{ gridArea: "remarks" }}>Remarks</span>
              </div>

              {loading &&
                Array.from({ length: 6 }, (_, i) => <div key={i} className="pv-skel"><span /></div>)}

              {!loading && DOCUMENTS.map((d) => {
                const v = form[d.key];
                if (!v) return null;
                return (
                  <div key={d.key} className="pv-row" data-status={v.status}>
                    {/* Document name */}
                    <div className="pv-doc" style={{ gridArea: "doc" }}>
                      <span className="pv-tile" data-tone={d.tone}><d.icon size={19} /></span>
                      <div>
                        <strong>{d.label}{d.required && <em title="Required">*</em>}</strong>
                        <small>{d.suggested}</small>
                      </div>
                    </div>

                    {/* View uploaded file */}
                    <div className="pv-view-cell" style={{ gridArea: "view" }}>
                      {files[d.key] ? (
                        <a href={files[d.key]} target="_blank" rel="noopener noreferrer"
                           className="pv-view" title="View document" aria-label={`View ${d.label}`}>
                          <Eye size={17} />
                        </a>
                      ) : (
                        <span className="pv-na" title="Nothing uploaded">N/A</span>
                      )}
                    </div>

                    {/* Status */}
                    <div className="pv-seg" role="radiogroup" aria-label={`${d.label} status`} style={{ gridArea: "status" }}>
                      {STATUSES.map((s) => (
                        <label key={s.value} className={`pv-opt ${s.value} ${v.status === s.value ? "on" : ""}`}>
                          <input
                            type="radio"
                            name={`${d.key}_status`}
                            value={s.value}
                            checked={v.status === s.value}
                            onChange={() => setField(d.key, "status", s.value)}
                          />
                          {s.label}
                        </label>
                      ))}
                    </div>

                    {/* Remarks */}
                    <input
                      type="text"
                      className="pv-remarks"
                      style={{ gridArea: "remarks" }}
                      name={`${d.key}_remarks`}
                      value={v.remarks}
                      onChange={(e) => setField(d.key, "remarks", e.target.value)}
                      placeholder="Add remarks…"
                      aria-label={`${d.label} remarks`}
                      autoComplete="off"
                      maxLength={MAX_REMARKS}
                    />
                  </div>
                );
              })}
            </div>
          )}

          {/* ---------- Buttons (always visible) ---------- */}
          <div className="pv-foot">
            <a href={LINKS.back} className="pv-btn pv-btn-ghost"><ArrowLeft size={16} /> Back</a>
            <button type="submit" className="pv-btn pv-btn-green" disabled={saving || loading || !!error || !dirty}>
              {saving ? <><Loader2 size={16} className="pv-spin" /> Saving…</> : <><Save size={16} /> Save Verification</>}
            </button>
          </div>
        </form>

        {toast && <div className={`pv-toast ${toast.type}`} role="status">{toast.text}</div>}
      </div>
    </AdminLayout>
  );
}