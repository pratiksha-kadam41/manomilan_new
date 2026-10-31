// src/pages/ProfileDetail.jsx  (page + backend calls in one file)
// npm install react-icons react-router-dom
import { useCallback, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import {
  FaArrowLeft, FaBirthdayCake, FaBriefcase, FaCalendarAlt, FaCommentDots, FaDownload,
  FaEdit, FaFileAlt, FaGraduationCap, FaHeartbeat, FaHome, FaIdCard, FaInfoCircle,
  FaImages, FaListUl, FaMapMarkerAlt, FaPhoneAlt, FaRedo, FaRulerVertical, FaStar, FaTrash,
  FaUserTie, FaUsers, FaVenusMars, FaWeight, FaAddressBook,
} from "react-icons/fa";
import "./ProfileDetail.css";

/* =====================================================================
   0. BACKEND CALLS + VALIDATION (was profileService.js)
   ===================================================================== */
// All backend calls + validation for the Profile Detail page.
// Set these in .env:  VITE_API_URL=https://aaryansmanomilan.com   VITE_ASSET_URL=https://aaryansmanomilan.com

const API_BASE = (import.meta.env.VITE_API_URL || "").replace(/\/+$/, "");
const ASSET_BASE = (import.meta.env.VITE_ASSET_URL || "").replace(/\/+$/, "");

class ApiError extends Error {
  constructor(message, status = 0) {
    super(message);
    this.name = "ApiError";
    this.status = status;
  }
}

/* ---------------- validation / safety helpers ---------------- */

// Only positive whole numbers are allowed as an id (blocks "abc", "-1", "1;DROP", etc.)
const isValidId = (id) => /^\d{1,12}$/.test(String(id ?? "")) && Number(id) > 0;

// Turns "uploads/x.png" into a full URL
const assetUrl = (path) => {
  if (!path || typeof path !== "string") return "";
  if (/^https?:\/\//i.test(path)) return path;
  return `${ASSET_BASE}/${path.replace(/^\/+/, "")}`;
};

// Allows only http/https links (blocks javascript: and data: links)
const safeUrl = (url) => {
  try {
    const u = new URL(url, window.location.origin);
    return ["http:", "https:"].includes(u.protocol) ? u.href : "";
  } catch {
    return "";
  }
};

/* ---------------- request wrapper ---------------- */

const authHeaders = () => {
  try {
    const token = localStorage.getItem("token"); // change if your admin token is stored elsewhere
    return token ? { Authorization: `Bearer ${token}` } : {};
  } catch {
    return {};
  }
};

const FALLBACK_MESSAGES = {
  401: "Your session has expired. Please log in again.",
  403: "You do not have permission to do this.",
  404: "Profile not found.",
  429: "Too many requests. Please wait a moment and try again.",
};

async function request(path, { method = "GET", signal, expect = "json" } = {}) {
  let res;
  try {
    res = await fetch(`${API_BASE}${path}`, {
      method,
      signal,
      credentials: "include",
      headers: { Accept: expect === "json" ? "application/json" : "*/*", ...authHeaders() },
    });
  } catch (err) {
    if (err.name === "AbortError") throw err;
    throw new ApiError("Cannot reach the server. Check your internet connection and try again.");
  }

  if (!res.ok) {
    let serverMessage = "";
    try {
      serverMessage = (await res.json())?.message || "";
    } catch {
      /* response was not JSON */
    }
    let message;
    if (res.status >= 500) message = "Server error. Please try again in a moment.";
    else if (res.status === 401) message = FALLBACK_MESSAGES[401];
    else message = serverMessage || FALLBACK_MESSAGES[res.status] || `Request failed (${res.status}).`;
    throw new ApiError(message, res.status);
  }

  if (expect === "blob") return res;
  try {
    return await res.json();
  } catch {
    throw new ApiError("The server sent an invalid response.", res.status);
  }
}

/* ---------------- public API ---------------- */

function normalizeProfile(p) {
  const docs = Array.isArray(p.documents) ? p.documents : [];
  return {
    ...p,
    _photo: assetUrl(p.profile_photo || p.photo || ""),
    photos: (Array.isArray(p.photos) ? p.photos : [])
      .filter((photo) => photo && typeof photo === "object")
      .map((photo, i) => ({
        id: photo.id ?? i,
        label: String(photo.label || `Photo ${i + 1}`),
        url: safeUrl(assetUrl(photo.url || photo.path || "")),
      }))
      .filter((photo) => photo.url),
    documents: docs
      .filter((d) => d && typeof d === "object")
      .map((d, i) => ({
        id: d.id ?? i,
        label: String(d.label || d.type || d.name || "Document"),
        url: d.url || d.file_path || d.path || "",
      })),
  };
}

async function getProfile(id, signal) {
  if (!isValidId(id)) throw new ApiError("This profile link is not valid.", 400);
  const json = await request(`/api/profiles/${id}`, { signal });
  const profile = json?.data ?? json;
  if (!profile || typeof profile !== "object" || Array.isArray(profile)) {
    throw new ApiError("The server sent an invalid response.");
  }
  return normalizeProfile(profile);
}

async function deleteDocument(profileId, docId) {
  if (!isValidId(profileId) || !/^[\w-]{1,64}$/.test(String(docId))) {
    throw new ApiError("Invalid document.", 400);
  }
  await request(`/api/profiles/${profileId}/documents/${docId}`, { method: "DELETE" });
}

async function downloadBiodata(profileId, regNo) {
  if (!isValidId(profileId)) throw new ApiError("Invalid profile.", 400);
  const res = await request(`/api/profiles/${profileId}/biodata`, { expect: "blob" });
  const blob = await res.blob();
  if (!blob.size) throw new ApiError("The BioData file is empty. Please try again.");

  const name = `BioData_${String(regNo || profileId).replace(/[^\w-]/g, "")}.pdf`;
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/* =====================================================================
   1. HELPERS
   ===================================================================== */
const NA = "N/A";

// read "a.b.c" paths safely
const pick = (obj, path) => String(path).split(".").reduce((o, k) => (o == null ? undefined : o[k]), obj);

const parseDate = (v) => {
  if (!v) return null;
  const s = String(v).trim();
  const m = /^(\d{2})-(\d{2})-(\d{4})$/.exec(s); // 29-01-1997
  const d = m ? new Date(+m[3], +m[2] - 1, +m[1]) : new Date(s);
  return Number.isNaN(d.getTime()) ? null : d;
};
const ageFrom = (d) => {
  const t = new Date();
  let a = t.getFullYear() - d.getFullYear();
  if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) a--;
  return a >= 0 && a < 120 ? a : null;
};
const longDate = (d) => `${d.getDate()} ${d.toLocaleString("en-GB", { month: "short" })}, ${d.getFullYear()}`;

// format a raw backend value for display; returns null when empty
const fmt = (v, type) => {
  if (v === null || v === undefined || typeof v === "object" && !Array.isArray(v)) return null;
  if (Array.isArray(v)) v = v.filter(Boolean).join(", ");
  const s = String(v).trim();
  if (!s) return null;
  if (type === "money") {
    const n = Number(s.replace(/,/g, ""));
    return Number.isFinite(n) ? `₹ ${n.toLocaleString("en-IN")}` : s;
  }
  if (type === "date") {
    const d = parseDate(s);
    return d ? longDate(d) : s;
  }
  return s;
};

/* =====================================================================
   2. FIELD CONFIG  — change only the  k:  value to match your backend field names
      l = label, k = backend key (dotted path allowed), t = type, wide = full width
   ===================================================================== */
const referenceSub = (p) => {
  const text = [fmt(pick(p, "reference_name")), fmt(pick(p, "reference_relation"))].filter(Boolean).join(" - ");
  return text ? `(${text})` : "";
};

const TABS = [
  {
    id: "personal", label: "Personal",
    sections: [
      { title: "About", icon: <FaInfoCircle />, fields: [
        { l: "Marital Status", k: "marital_status" }, { l: "Caste", k: "caste" }, { l: "Sub Caste", k: "sub_caste" },
        { l: "Mother Tongue", k: "mother_tongue" }, { l: "Languages Known", k: "languages_known" },
      ] },
      { title: "Contact Details", icon: <FaAddressBook />, fields: [
        { l: "Contact No", k: "mobile" }, { l: "Address", k: "address", wide: true },
        { l: "Father's Contact", k: "father_phone" }, { l: "Mother's Contact", k: "mother_phone" },
        { l: "Reference Contact", k: "reference_phone", sub: referenceSub },
      ] },
      { title: "Health & Lifestyle", icon: <FaHeartbeat />, fields: [
        { l: "Blood Group", k: "blood_group" }, { l: "Spectacles/Lenses", k: "spectacles" },
        { l: "Diet", k: "diet" }, { l: "Smoking", k: "smoking" }, { l: "Drinking", k: "drinking" },
      ] },
    ],
  },
  {
    id: "professional", label: "Professional",
    sections: [
      { title: "Education", icon: <FaGraduationCap />, fields: [
        { l: "Highest Education", k: "education" }, { l: "University / College", k: "university" },
        { l: "Additional Education 1", k: "additional_education_1" }, { l: "Additional Education 2", k: "additional_education_2" },
      ] },
      { title: "Profession", icon: <FaBriefcase />, fields: [
        { l: "Profession", k: "profession" }, { l: "Designation", k: "designation" }, { l: "Organisation", k: "organisation" },
        { l: "Field of Working", k: "field_of_working" }, { l: "Length of Employment", k: "length_of_employment" },
        { l: "Monthly Income", k: "monthly_income", t: "money" }, { l: "Annual Income", k: "annual_income", t: "money" },
      ] },
    ],
  },
  {
    id: "family", label: "Family",
    sections: [
      { title: "Family Details", icon: <FaUsers />, fields: [
        { l: "Father's Name", k: "father_name", suffix: "father_living" }, { l: "Father's Occupation", k: "father_occupation" },
        { l: "Father's Native Place", k: "father_native_place" }, { l: "Father's Contact", k: "father_phone" },
        { l: "Mother's Name", k: "mother_name", suffix: "mother_living" }, { l: "Mother's Occupation", k: "mother_occupation" },
        { l: "Mother's Native Place", k: "mother_native_place" }, { l: "Mother's Contact", k: "mother_phone" },
        { l: "Brothers", k: "brothers" }, { l: "Brothers Married", k: "brothers_married" },
        { l: "Sisters", k: "sisters" }, { l: "Sisters Married", k: "sisters_married" },
        { l: "Parents Intercaste Marriage", k: "parents_intercaste" }, { l: "Parents Different Caste", k: "parents_different_caste" },
        { l: "Family Financial Status", k: "family_financial_status" }, { l: "Family Annual Income", k: "family_income", t: "money" },
        { l: "Family Values", k: "family_values" },
      ] },
    ],
  },
  {
    id: "horoscope", label: "Horoscope",
    sections: [
      { title: "Horoscope Details", icon: <FaStar />, fields: [
        { l: "Date of Birth", k: "horoscope_dob", t: "date" }, { l: "Time of Birth", k: "birth_time" }, { l: "Place of Birth", k: "birth_place" },
        { l: "Rashi", k: "rashi" }, { l: "Nakshatra", k: "nakshatra" }, { l: "Nadi", k: "nadi" },
        { l: "Gan", k: "gan" }, { l: "Gotra", k: "gotra" }, { l: "Kundali", k: "kundali" },
        { l: "Horoscope Caste", k: "horoscope_caste" }, { l: "Horoscope Sub Caste", k: "horoscope_sub_caste" }, { l: "Charan", k: "charan" },
        { l: "Match Patrika", k: "match_patrika" },
      ] },
    ],
  },
  { id: "photos", label: "Photos" },
  { id: "documents", label: "Documents" },
  {
    id: "preference", label: "Preference",
    sections: [
      { title: "Partner Preferences", icon: <FaListUl />, fields: [
        { l: "Age Range", k: "pref_age_range" }, { l: "Height Range", k: "pref_height_range" },
        { l: "Preferred Religion", k: "pref_religion" }, { l: "Preferred Caste", k: "pref_caste" },
        { l: "Preferred Sub-Caste", k: "pref_sub_caste" }, { l: "Preferred Education", k: "pref_education" },
        { l: "Working Partner", k: "pref_working_partner" }, { l: "Preferred Working Field", k: "pref_working_field" },
        { l: "Expected Income", k: "pref_income" }, { l: "Family Type", k: "pref_family_type" },
        { l: "Financial Background", k: "pref_financial_background" }, { l: "Family Values", k: "pref_family_values" },
        { l: "Preferred Country", k: "pref_country" }, { l: "Preferred State", k: "pref_state" }, { l: "Preferred City", k: "pref_city" },
      ] },
      { title: "Other Expectations", icon: <FaCommentDots />, kind: "text", k: "other_expectations", empty: "No other specific expectations mentioned." },
    ],
  },
];

/* =====================================================================
   3. SMALL COMPONENTS
   ===================================================================== */
function Field({ f, profile }) {
  const value = fmt(pick(profile, f.k), f.t);
  const suffix = f.suffix ? fmt(pick(profile, f.suffix)) : null;
  const sub = value && f.sub ? f.sub(profile) : "";
  return (
    <div className={`pd-field${f.wide ? " pd-wide" : ""}`}>
      <span className="pd-label">{f.l}</span>
      <span className={`pd-value${value ? "" : " pd-empty"}`}>
        {value ? <>{value}{suffix && <em> ({suffix})</em>}</> : NA}
      </span>
      {sub && <small className="pd-sub">{sub}</small>}
    </div>
  );
}

function Section({ s, profile }) {
  return (
    <section className="pd-section">
      <h3 className="pd-section-title"><span className="pd-section-ic">{s.icon}</span>{s.title}</h3>
      {s.kind === "text" ? (
        <p className="pd-text">{fmt(pick(profile, s.k)) || s.empty}</p>
      ) : (
        <div className="pd-grid">
          {s.fields.map((f) => <Field key={f.k + f.l} f={f} profile={profile} />)}
        </div>
      )}
    </section>
  );
}

function Documents({ docs, onDelete, deletingId }) {
  return (
    <section className="pd-section">
      <h3 className="pd-section-title"><span className="pd-section-ic"><FaFileAlt /></span>Uploaded Documents</h3>
      {docs.length === 0 ? (
        <p className="pd-text">No documents have been uploaded yet.</p>
      ) : (
        <ul className="pd-docs">
          {docs.map((d) => {
            const url = safeUrl(assetUrl(d.url));
            return (
              <li key={d.id}>
                <span className="pd-doc-ic">{/birth/i.test(d.label) ? <FaCalendarAlt /> : <FaIdCard />}</span>
                {url ? <a href={url} target="_blank" rel="noopener noreferrer">{d.label}</a> : <span>{d.label}</span>}
                <button type="button" className="pd-icon-btn" aria-label={`Delete ${d.label}`}
                  disabled={deletingId === d.id} onClick={() => onDelete(d)}>
                  <FaTrash />
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

function Photos({ photos }) {
  return (
    <section className="pd-section">
      <h3 className="pd-section-title"><span className="pd-section-ic"><FaImages /></span>Photos Uploaded by Candidate</h3>
      {photos.length === 0 ? (
        <p className="pd-text">No photos have been uploaded for this profile.</p>
      ) : (
        <div className="pd-photos">
          {photos.map((photo) => (
            <a key={photo.id} className="pd-photo-card" href={photo.url} target="_blank" rel="noopener noreferrer">
              <img src={photo.url} alt={photo.label} loading="lazy" />
              <span>{photo.label}</span>
            </a>
          ))}
        </div>
      )}
    </section>
  );
}

function Sidebar({ p, onEdit, onDownload, busy }) {
  const [imgFailed, setImgFailed] = useState(false);
  const name = fmt(p.full_name) || "Unnamed profile";
  const initials = name.split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
  const dob = parseDate(p.dob);
  const age = dob ? ageFrom(dob) : null;
  const weight = fmt(p.weight);

  const facts = [
    [<FaVenusMars />, fmt(p.gender)],
    [<FaBirthdayCake />, dob ? `${longDate(dob)}${age !== null ? ` (${age} years)` : ""}` : null],
    [<FaRulerVertical />, fmt(p.height)],
    [<FaWeight />, weight ? (/^\d+(\.\d+)?$/.test(weight) ? `${weight} kg` : weight) : null],
    [<FaUserTie />, fmt(p.profession)],
    [<FaMapMarkerAlt />, fmt(p.city)],
    [<FaPhoneAlt />, fmt(p.mobile)],
    [<FaHome />, fmt(p.address)],
  ].filter(([, text]) => text);

  return (
    <aside className="pd-side">
      <div className="pd-banner" />
      <div className="pd-avatar">
        {p._photo && !imgFailed
          ? <img src={p._photo} alt={name} onError={() => setImgFailed(true)} />
          : <span aria-hidden="true">{initials}</span>}
      </div>
      <h2 className="pd-name">{name}</h2>
      {fmt(p.registration_no) && <div className="pd-reg">{fmt(p.registration_no)}</div>}
      <ul className="pd-facts">
        {facts.map(([icon, text], i) => <li key={i}><span className="pd-fact-ic">{icon}</span><span>{text}</span></li>)}
      </ul>
      <div className="pd-actions">
        <button type="button" className="pd-btn pd-btn-teal" onClick={onEdit}><FaEdit /> Edit Profile</button>
        <button type="button" className="pd-btn pd-btn-green" onClick={onDownload} disabled={busy}>
          <FaDownload /> {busy ? "Preparing…" : "BioData Download"}
        </button>
      </div>
    </aside>
  );
}

function Skeleton() {
  return (
    <div className="pd-layout" aria-busy="true" aria-label="Loading profile">
      <div className="pd-side"><div className="pd-banner" />
        <div className="pd-avatar pd-shimmer" /><div className="pd-sk pd-sk-name pd-shimmer" />
        {[1, 2, 3, 4, 5].map((i) => <div key={i} className="pd-sk pd-shimmer" />)}
      </div>
      <div className="pd-main">
        <div className="pd-sk pd-sk-tabs pd-shimmer" />
        {[1, 2, 3, 4, 5, 6].map((i) => <div key={i} className="pd-sk pd-shimmer" />)}
      </div>
    </div>
  );
}

function StateCard({ title, message, onRetry }) {
  return (
    <div className="pd-state" role="alert">
      <h2>{title}</h2>
      <p>{message}</p>
      <div>
        {onRetry && <button type="button" className="pd-btn pd-btn-teal" onClick={onRetry}><FaRedo /> Try again</button>}
        <Link to="/profiles/allProfiles" className="pd-btn pd-btn-ghost">Back to Profiles</Link>
      </div>
    </div>
  );
}

/* =====================================================================
   4. PAGE
   ===================================================================== */
export default function ProfileDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const idOk = isValidId(id);

  const [state, setState] = useState({ status: "loading", profile: null, error: "", notFound: false });
  const [tab, setTab] = useState("personal");
  const [notice, setNotice] = useState(null); // { type: "success" | "error", text }
  const [downloading, setDownloading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);

  const load = useCallback(async (signal) => {
    setState({ status: "loading", profile: null, error: "", notFound: false });
    try {
      const profile = await getProfile(id, signal);
      setState({ status: "ready", profile, error: "", notFound: false });
    } catch (err) {
      if (err.name === "AbortError") return;
      setState({ status: "error", profile: null, error: err.message || "Something went wrong.", notFound: err.status === 404 });
    }
  }, [id]);

  useEffect(() => {
    setTab("personal");
    setNotice(null);
    if (!idOk) return undefined;
    const controller = new AbortController();
    load(controller.signal);
    return () => controller.abort();
  }, [idOk, load]);

  // success messages disappear by themselves
  useEffect(() => {
    if (notice?.type !== "success") return undefined;
    const t = setTimeout(() => setNotice(null), 4000);
    return () => clearTimeout(t);
  }, [notice]);

  const profile = state.profile;

  const handleDownload = async () => {
    setDownloading(true);
    setNotice(null);
    try {
      await downloadBiodata(id, profile?.registration_no);
      setNotice({ type: "success", text: "BioData downloaded." });
    } catch (err) {
      setNotice({ type: "error", text: err.message || "Could not download the BioData." });
    } finally {
      setDownloading(false);
    }
  };

  const handleDeleteDoc = async (doc) => {
    if (!window.confirm(`Delete "${doc.label}"? This cannot be undone.`)) return;
    setDeletingId(doc.id);
    setNotice(null);
    try {
      await deleteDocument(id, doc.id);
      setState((s) => ({ ...s, profile: { ...s.profile, documents: s.profile.documents.filter((d) => d.id !== doc.id) } }));
      setNotice({ type: "success", text: `${doc.label} deleted.` });
    } catch (err) {
      setNotice({ type: "error", text: err.message || "Could not delete the document." });
    } finally {
      setDeletingId(null);
    }
  };

  let body;
  if (!idOk) {
    body = <StateCard title="Invalid profile link" message="The profile id in this link is not valid. Open the profile from the Profiles list." />;
  } else if (state.status === "loading") {
    body = <Skeleton />;
  } else if (state.status === "error") {
    body = <StateCard title={state.notFound ? "Profile not found" : "Could not load this profile"}
      message={state.error} onRetry={state.notFound ? undefined : () => load()} />;
  } else {
    const active = TABS.find((t) => t.id === tab) || TABS[0];
    body = (
      <div className="pd-layout">
        <Sidebar p={profile} busy={downloading} onDownload={handleDownload} onEdit={() => navigate(`/profiles/${id}/edit`)} />
        <div className="pd-main">
          <div className="pd-tabs" role="tablist" aria-label="Profile sections">
            {TABS.map((t) => (
              <button key={t.id} type="button" role="tab" id={`pd-tab-${t.id}`} aria-selected={tab === t.id}
                aria-controls="pd-panel" className={`pd-tab${tab === t.id ? " active" : ""}`} onClick={() => setTab(t.id)}>
                {t.label}
              </button>
            ))}
          </div>
          <div id="pd-panel" role="tabpanel" aria-labelledby={`pd-tab-${active.id}`} className="pd-panel">
            {active.id === "photos"
              ? <Photos photos={profile.photos} />
              : active.id === "documents"
                ? <Documents docs={profile.documents} onDelete={handleDeleteDoc} deletingId={deletingId} />
              : active.sections.map((s) => <Section key={s.title} s={s} profile={profile} />)}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="pd">
      <Link to="/profiles/allProfiles" className="pd-back"><FaArrowLeft /> Back to Profiles</Link>
      {notice && (
        <div className={`pd-alert pd-alert-${notice.type}`} role="alert">
          <span>{notice.text}</span>
          <button type="button" aria-label="Dismiss message" onClick={() => setNotice(null)}>×</button>
        </div>
      )}
      {body}
    </div>
  );
}
