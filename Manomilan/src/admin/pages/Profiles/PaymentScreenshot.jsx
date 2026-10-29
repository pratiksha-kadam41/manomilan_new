import { useEffect, useRef, useState } from "react";
import {
  ArrowLeft, BadgeCheck, CheckCircle2, Clock, Download, ImageOff, UploadCloud, X, ZoomIn,
  Loader2, AlertTriangle, Phone, Check, Upload,
} from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import { apiUrl } from "../../../api";
import { getSession, clearSession } from "../../../auth";
import "./payment.css";

/* =====================================================================
   1. BACKEND SETTINGS  (the only part you need to adjust later)
   ---------------------------------------------------------------------
   Load:    GET  {BASE}/profiles/:id/payment-screenshots
            → {
                profile:     { id, full_name, registration_number, mobile },
                screenshots: [ { id, type, status, image_url }, ... ]
              }
              type   = "payment" (normal registration payment) or "melava" (Melava registration payment)
              status = "pending" or "verified"
   Verify:  POST {BASE}/profiles/:id/payment-screenshots/:screenshotId/verify
   Upload:  POST {BASE}/profiles/:id/payment-screenshot
            (multipart/form-data, the file is sent in the field "payment_screenshot")
   ===================================================================== */
const BASE = apiUrl;
const LINKS = { back: "/admin/profiles" };

const MAX_FILE_MB = 5;                                        // keep in step with your Laravel validation
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp"];

const authHeaders = () => {
  const s = getSession();
  return { Accept: "application/json", ...(s ? { Authorization: `Bearer ${s.token}` } : {}) };
};

// Sends the person back to login if the server says the session is invalid
const guard = (res) => {
  if (res.status === 401) {
    clearSession();
    window.location.assign("/login?expired=1");
    throw new Error("Session expired.");
  }
  if (res.status === 404) throw new Error("This item was not found.");
  if (res.status === 413 || res.status === 422) throw new Error("The server did not accept this. Please check the file and try again.");
  if (!res.ok) throw new Error("Server error. Please try again.");
  return res;
};

/* ---- Security helpers ---- */
const isValidId = (id) => /^\d{1,12}$/.test(String(id ?? ""));      // only whole numbers are accepted as an id

// Only http(s) links or site-relative paths are used (blocks "javascript:" and "data:" links)
const safeUrl = (url) => {
  if (!url || typeof url !== "string") return "";
  try {
    const u = new URL(url, window.location.origin);
    return u.protocol === "https:" || u.protocol === "http:" ? u.href : "";
  } catch {
    return "";
  }
};

const cleanStatus = (s) => (s === "verified" ? "verified" : "pending");
const cleanType = (t) => (t === "melava" ? "melava" : "payment");

async function getPayments(id) {
  const res = guard(await fetch(`${BASE}/profiles/${encodeURIComponent(id)}/payment-screenshots`, { headers: authHeaders() }));
  return res.json();
}

async function verifyPayment(profileId, screenshotId, type) {
  guard(await fetch(`${BASE}/profiles/${encodeURIComponent(profileId)}/payment-screenshots/${encodeURIComponent(screenshotId)}/verify`, {
    method: "POST",
    headers: { ...authHeaders(), "Content-Type": "application/json" },
    body: JSON.stringify({ type }),
  }));
}

async function uploadScreenshot(id, file) {
  const body = new FormData();
  body.append("payment_screenshot", file);
  guard(await fetch(`${BASE}/profiles/${encodeURIComponent(id)}/payment-screenshot`, {
    method: "POST",
    headers: authHeaders(),                       // no Content-Type here: the browser sets it for file uploads
    body,
  }));
}

/* ---------- Small pieces ---------- */
const initials = (name = "") =>
  name.split(" ").filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();

const formatSize = (bytes) => (bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

function StatusBadge({ status, label }) {
  const ok = status === "verified";
  return (
    <span className={`ps-badge ${ok ? "ok" : "wait"}`}>
      {ok ? <CheckCircle2 size={14} /> : <Clock size={14} />} {label}
    </span>
  );
}

function Screenshot({ item, title, busy, onVerify, onOpen }) {
  const [broken, setBroken] = useState(false);
  const verified = item.status === "verified";
  return (
    <article className={`ps-shot ${verified ? "is-ok" : "is-wait"}`}>
      <header className="ps-shot-head">
        <h3>{title}</h3>
        <StatusBadge status={item.status} label={verified ? "Verified" : "Pending"} />
      </header>

      {item.image_url && !broken ? (
        <button type="button" className="ps-img" onClick={() => onOpen({ url: item.image_url, title })} aria-label={`Enlarge ${title}`}>
          <img src={item.image_url} alt={title} loading="lazy" referrerPolicy="no-referrer" onError={() => setBroken(true)} />
          <span className="ps-zoom"><ZoomIn size={16} /> Click to enlarge</span>
        </button>
      ) : (
        <div className="ps-img ps-img-empty"><ImageOff size={32} /><span>Image not available</span></div>
      )}

      <footer className="ps-shot-foot">
        {item.image_url && (
          <a className="ps-btn ps-btn-green" href={item.image_url} download target="_blank" rel="noopener noreferrer">
            <Download size={16} /> Download
          </a>
        )}
        {!verified && (
          <button type="button" className="ps-btn ps-btn-teal" onClick={() => onVerify(item, title)} disabled={busy}>
            {busy ? <><Loader2 size={16} className="ps-spin" /> Verifying…</> : <><Check size={16} /> Verify Payment</>}
          </button>
        )}
      </footer>
    </article>
  );
}

/* ====================================================================
   2. THE PAGE
   ==================================================================== */
export default function PaymentScreenshot({ params }) {
  const id = params?.id;
  const idOk = isValidId(id);

  const [profile, setProfile] = useState(null);
  const [shots, setShots] = useState([]);
  const [loading, setLoading] = useState(idOk);
  const [loadError, setLoadError] = useState("");
  const error = idOk ? loadError : "This profile link is not valid.";
  const [reloadKey, setReloadKey] = useState(0);       // change it to load the data again

  const [confirm, setConfirm] = useState(null);        // { item, title } waiting for "Yes, verify"
  const [verifyingId, setVerifyingId] = useState(null);
  const [viewer, setViewer] = useState(null);          // { url, title } shown full-size

  const [file, setFile] = useState(null);
  const [fileError, setFileError] = useState("");
  const [preview, setPreview] = useState("");
  const [uploading, setUploading] = useState(false);
  const fileInput = useRef(null);

  const [toast, setToast] = useState(null);
  const toastTimer = useRef(null);
  const showToast = (type, text) => {
    setToast({ type, text });
    clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(null), 3500);
  };
  useEffect(() => () => clearTimeout(toastTimer.current), []);

  /* ---- load from the backend ---- */
  useEffect(() => {
    if (!idOk) return;
    let cancelled = false;
    getPayments(id)
      .then((data) => {
        if (cancelled) return;
        setProfile(data.profile || {});
        setShots(
          (Array.isArray(data.screenshots) ? data.screenshots : []).map((s) => ({
            id: s.id,
            type: cleanType(s.type),
            status: cleanStatus(s.status),
            image_url: safeUrl(s.image_url),
          })),
        );
      })
      .catch((e) => { if (!cancelled) setLoadError(e.message); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [id, idOk, reloadKey]);

  const reload = () => { setLoadError(""); setLoading(true); setReloadKey((k) => k + 1); };

  /* ---- close pop-ups with the Esc key ---- */
  useEffect(() => {
    if (!viewer && !confirm) return;
    const onKey = (e) => { if (e.key === "Escape") { setViewer(null); if (!verifyingId) setConfirm(null); } };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [viewer, confirm, verifyingId]);

  /* ---- verify ---- */
  const doVerify = async () => {
    if (!confirm || verifyingId) return;
    const { item } = confirm;
    setVerifyingId(item.id);
    try {
      await verifyPayment(id, item.id, item.type);
      setShots((list) => list.map((s) => (s.id === item.id ? { ...s, status: "verified" } : s)));
      showToast("success", "Payment verified.");
      setConfirm(null);
    } catch (e) {
      showToast("error", e.message);
    } finally {
      setVerifyingId(null);
    }
  };

  /* ---- choose + upload a file ---- */
  const clearFile = () => {
    setFile(null);
    setFileError("");
    if (preview) URL.revokeObjectURL(preview);
    setPreview("");
    if (fileInput.current) fileInput.current.value = "";
  };

  const onPick = (e) => {
    const picked = e.target.files?.[0];
    clearFile();
    if (!picked) return;
    if (!ALLOWED_TYPES.includes(picked.type)) { setFileError("Please choose a JPG, PNG or WEBP image."); return; }
    if (picked.size > MAX_FILE_MB * 1024 * 1024) { setFileError(`The image must be smaller than ${MAX_FILE_MB} MB.`); return; }
    setFile(picked);
    setPreview(URL.createObjectURL(picked));
  };

  useEffect(() => () => { if (preview) URL.revokeObjectURL(preview); }, [preview]);

  const doUpload = async (e) => {
    e.preventDefault();
    if (!file || uploading) return;
    setUploading(true);
    try {
      await uploadScreenshot(id, file);
      if (BASE) {
        reload();                                                   // real backend: fetch the new list
      } else {                                                      // sample mode: show it locally
        setShots((list) => [...list, { id: Date.now(), type: "payment", status: "pending", image_url: URL.createObjectURL(file) }]);
      }
      clearFile();
      showToast("success", "Screenshot uploaded. It needs to be verified.");
    } catch (err) {
      showToast("error", err.message);
    } finally {
      setUploading(false);
    }
  };

  /* ---- what to show in the top badges ---- */
  const paymentShots = shots.filter((s) => s.type === "payment");
  const melavaShot = shots.find((s) => s.type === "melava");
  const paymentVerified = paymentShots.some((s) => s.status === "verified");
  let n = 0;                                                         // numbers the normal screenshots: #1, #2 …

  return (
    <AdminLayout currentPath="/admin/profiles">
      <div className="ps-page">
        {/* ---------- Title ---------- */}
        <div className="ps-top">
          <div>
            <h1>Payment Screenshots</h1>
            <p>Check the payment proof uploaded by the candidate and verify it.</p>
          </div>
          <a href={LINKS.back} className="ps-btn ps-btn-teal"><ArrowLeft size={16} /> Back to Profiles</a>
        </div>

        {error ? (
          <div className="ps-card ps-state">
            <AlertTriangle size={34} />
            <p>{error}</p>
            {idOk && <button type="button" className="ps-btn ps-btn-teal" onClick={reload}>Try again</button>}
          </div>
        ) : (
          <>
            {/* ---------- Profile details ---------- */}
            <section className="ps-card ps-profile">
              {loading || !profile ? (
                <div className="ps-skel"><span /></div>
              ) : (
                <>
                  <div className="ps-avatar">{initials(profile.full_name)}</div>
                  <div className="ps-who">
                    <h2>{profile.full_name}</h2>
                    <p>
                      <span className="ps-regno">{profile.registration_number}</span>
                      <span className="ps-mobile"><Phone size={13} /> {profile.mobile}</span>
                    </p>
                  </div>
                  <div className="ps-badges">
                    {paymentShots.length > 0 && (
                      <span className={`ps-big ${paymentVerified ? "ok" : "wait"}`}>
                        {paymentVerified ? <BadgeCheck size={18} /> : <Clock size={18} />}
                        {paymentVerified ? "Payment Verified" : "Payment Pending"}
                      </span>
                    )}
                    {melavaShot && (
                      <span className={`ps-big ${melavaShot.status === "verified" ? "ok" : "wait"}`}>
                        {melavaShot.status === "verified" ? <BadgeCheck size={18} /> : <Clock size={18} />}
                        Melava Registration {melavaShot.status === "verified" ? "Verified" : "Pending"}
                      </span>
                    )}
                  </div>
                </>
              )}
            </section>

            {/* ---------- Screenshots ---------- */}
            <div className="ps-grid">
              {loading && [0, 1].map((i) => <div key={i} className="ps-card ps-skel ps-skel-card"><span /></div>)}

              {!loading && shots.length === 0 && (
                <div className="ps-card ps-empty"><ImageOff size={34} /><p>No payment screenshot has been uploaded yet.</p></div>
              )}

              {!loading && shots.map((s) => {
                if (s.type === "payment") n += 1;
                const title = s.type === "melava" ? "Melava Registration Screenshot" : `Screenshot #${n}`;
                return (
                  <Screenshot
                    key={s.id}
                    item={s}
                    title={title}
                    busy={verifyingId === s.id}
                    onVerify={(item, t) => setConfirm({ item, title: t })}
                    onOpen={setViewer}
                  />
                );
              })}
            </div>

            {/* ---------- Upload another screenshot ---------- */}
            {!loading && (
              <form className="ps-card ps-upload" onSubmit={doUpload}>
                <h3>Upload Additional Screenshot</h3>
                <p>If the user uploaded the wrong screenshot, you can upload a new one here. It will be added to the list and will need to be verified.</p>

                <label className={`ps-drop ${file ? "has-file" : ""} ${fileError ? "has-error" : ""}`}>
                  <input ref={fileInput} type="file" accept={ALLOWED_TYPES.join(",")} onChange={onPick} disabled={uploading} />
                  {preview ? (
                    <img src={preview} alt="Selected screenshot preview" className="ps-preview" />
                  ) : (
                    <UploadCloud size={30} />
                  )}
                  <span className="ps-drop-text">
                    {file ? <><strong>{file.name}</strong><small>{formatSize(file.size)}</small></> : <><strong>Choose an image</strong><small>JPG, PNG or WEBP · up to {MAX_FILE_MB} MB</small></>}
                  </span>
                </label>
                {fileError && <p className="ps-err" role="alert">{fileError}</p>}

                <div className="ps-upload-actions">
                  {file && <button type="button" className="ps-btn ps-btn-ghost" onClick={clearFile} disabled={uploading}>Remove</button>}
                  <button type="submit" className="ps-btn ps-btn-teal" disabled={!file || uploading}>
                    {uploading ? <><Loader2 size={16} className="ps-spin" /> Uploading…</> : <><Upload size={16} /> Upload</>}
                  </button>
                </div>
              </form>
            )}
          </>
        )}

        {/* ---------- Confirm "Verify Payment" ---------- */}
        {confirm && (
          <div className="ps-overlay" onMouseDown={(e) => { if (e.target === e.currentTarget && !verifyingId) setConfirm(null); }}>
            <div className="ps-modal" role="dialog" aria-modal="true" aria-labelledby="ps-confirm-title">
              <div className="ps-modal-icon"><BadgeCheck size={30} /></div>
              <h3 id="ps-confirm-title">Verify this payment?</h3>
              <p>You are about to mark <strong>{confirm.title}</strong> as verified. Please make sure the amount and the name in the screenshot are correct.</p>
              <div className="ps-modal-actions">
                <button type="button" className="ps-btn ps-btn-ghost" onClick={() => setConfirm(null)} disabled={!!verifyingId}>Cancel</button>
                <button type="button" className="ps-btn ps-btn-green" onClick={doVerify} disabled={!!verifyingId}>
                  {verifyingId ? <><Loader2 size={16} className="ps-spin" /> Verifying…</> : "Yes, verify"}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ---------- Full-size image ---------- */}
        {viewer && (
          <div className="ps-overlay ps-lightbox" onMouseDown={(e) => { if (e.target === e.currentTarget) setViewer(null); }}>
            <button type="button" className="ps-close" onClick={() => setViewer(null)} aria-label="Close"><X size={22} /></button>
            <img src={viewer.url} alt={viewer.title} referrerPolicy="no-referrer" />
          </div>
        )}

        {toast && <div className={`ps-toast ${toast.type}`} role="status">{toast.text}</div>}
      </div>
    </AdminLayout>
  );
}