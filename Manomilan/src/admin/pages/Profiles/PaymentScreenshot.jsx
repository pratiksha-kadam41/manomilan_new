import { useEffect, useRef, useState } from "react";
import {
  ArrowLeft, BadgeCheck, CheckCircle2, Clock, Download, ImageOff, UploadCloud, X, ZoomIn,
  Loader2, AlertTriangle, Phone, Check, Upload, FileText,
} from "lucide-react";
import AdminLayout from "../../components/AdminLayout";
import RecordEditorName from "../../components/RecordEditorName";
import { apiMediaUrl, apiUrl } from "../../../api";
import { getSession, clearSession } from "../../../auth";
import { useAdminPermission } from "../../../permission-context";
import "./payment.css";

/* =====================================================================
   1. BACKEND SETTINGS  (the only part you need to adjust later)
   ---------------------------------------------------------------------
   Load:    GET  {BASE}/profiles/:id/payment-screenshots
            → {
                profile:     { id, full_name, registration_number, mobile },
                screenshots: [ { id, type, status, image_url, payment_details }, ... ]
              }
              payment_details is a JSON record of amount, date, method, reference, remarks and proof path
              screenshots contains every payment entry for the profile
              status = "pending" or "verified"
   Verify:  POST {BASE}/profiles/:id/payment-screenshots/:screenshotId/verify
   Upload:  POST {BASE}/profiles/:id/payment-screenshot
            (multipart/form-data, the file is sent in the field "payment_screenshot")
   ===================================================================== */
const BASE = apiUrl;
const LINKS = { back: "/admin/profiles" };

const MAX_FILE_MB = 5;                                        // keep in step with your Laravel validation
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp", "application/pdf"];
const todayIsoDate = () => {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
};

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

const cleanStatus = (s) => (["verified", "approved"].includes(s) ? "verified" : s === "rejected" ? "rejected" : "pending");
const cleanType = (t) => (["melava", "booklet", "combined"].includes(t) ? t : "payment");

async function getPayments(id) {
  const res = guard(await fetch(`${BASE}/profiles/${encodeURIComponent(id)}/payment-screenshots`, { headers: authHeaders() }));
  return res.json();
}

async function verifyPayment(profileId, screenshotId, type, melavaId) {
  guard(await fetch(`${BASE}/profiles/${encodeURIComponent(profileId)}/payment-screenshots/${encodeURIComponent(screenshotId)}/verify`, {
    method: "POST",
    headers: { ...authHeaders(), "Content-Type": "application/json" },
    body: JSON.stringify({ type, ...(melavaId ? { melava_id: melavaId } : {}) }),
  }));
}

async function uploadScreenshot(id, file, details) {
  const body = new FormData();
  body.append("payment_screenshot", file);
  Object.entries(details).forEach(([key, value]) => body.append(`payment_details[${key}]`, value));
  guard(await fetch(`${BASE}/profiles/${encodeURIComponent(id)}/payment-screenshot`, {
    method: "POST",
    headers: authHeaders(),                       // no Content-Type here: the browser sets it for file uploads
    body,
  }));
}

/* ---------- Small pieces ---------- */
const initials = (name) =>
  String(name || "Profile").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();

const formatSize = (bytes) => (bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

function StatusBadge({ status, label }) {
  const ok = status === "verified";
  return (
    <span className={`ps-badge ${ok ? "ok" : status === "rejected" ? "rejected" : "wait"}`}>
      {ok ? <CheckCircle2 size={14} /> : status === "rejected" ? <X size={14} /> : <Clock size={14} />} {label}
    </span>
  );
}

function Screenshot({ item, title, busy, onVerify, onOpen, canVerify }) {
  const [broken, setBroken] = useState(false);
  const verified = item.status === "verified";
  const isPdf = /\.pdf(?:[?#]|$)/i.test(item.image_url || "");
  const details = item.payment_details || {};
  const hasDetails = Object.values(details).some((value) => value !== null && value !== "");
  return (
    <article className={`ps-shot ${verified ? "is-ok" : "is-wait"}`}>
      <header className="ps-shot-head">
        <h3>{title}</h3>
        <StatusBadge status={item.status} label={item.status === "verified" ? "Verified" : item.status === "rejected" ? "Rejected" : "Pending"} />
      </header>
      <RecordEditorName record={item} />

      {item.image_url && isPdf ? (
        <iframe className="ps-img ps-pdf" src={item.image_url} title={`${title} PDF`} />
      ) : item.image_url && !broken ? (
        <button type="button" className="ps-img" onClick={() => onOpen({ url: item.image_url, title })} aria-label={`Enlarge ${title}`}>
          <img src={item.image_url} alt={title} loading="lazy" referrerPolicy="no-referrer" onError={() => setBroken(true)} />
          <span className="ps-zoom"><ZoomIn size={16} /> Click to enlarge</span>
        </button>
      ) : (
        <div className="ps-img ps-img-empty"><ImageOff size={32} /><span>Image not available</span></div>
      )}

      {hasDetails ? (
        <dl className="ps-payment-details">
          {details.category && <div><dt>Category</dt><dd>{details.category.replaceAll("_", " ")}</dd></div>}
          {details.amount !== null && details.amount !== undefined && details.amount !== "" && (
            <div><dt>Amount</dt><dd>{details.amount}</dd></div>
          )}
          {details.payment_date && <div><dt>Payment date</dt><dd>{details.payment_date}</dd></div>}
          {details.payment_method && <div><dt>Method</dt><dd>{details.payment_method}</dd></div>}
          {details.reference_number && <div><dt>Reference</dt><dd>{details.reference_number}</dd></div>}
          {details.remarks && <div className="ps-payment-remarks"><dt>Remarks</dt><dd>{details.remarks}</dd></div>}
        </dl>
      ) : null}

      <footer className="ps-shot-foot">
        {item.image_url && (
          <a className="ps-btn ps-btn-green" href={item.image_url} download>
            <Download size={16} /> Download
          </a>
        )}
        {!verified && canVerify && (
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
  const canViewPayments = useAdminPermission("view", null, "payments_verification");
  const canCreatePayments = useAdminPermission("create", null, "payments_verification");
  const canApprovePayments = useAdminPermission("approve", null, "payments_verification");

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
  const [paymentDetails, setPaymentDetails] = useState({
    category: "profile",
    amount: "",
    payment_date: todayIsoDate(),
    payment_method: "",
    reference_number: "",
    remarks: "",
  });
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
    if (!idOk || !canViewPayments) return undefined;
    let cancelled = false;
    getPayments(id)
      .then((data) => {
        if (cancelled) return;
        setProfile(data.profile || {});
        setShots(
          (Array.isArray(data.screenshots) ? data.screenshots : []).map((s) => ({
            id: s.id,
            type: cleanType(s.type),
            status: cleanStatus(s.payment_details?.verification_status || s.status),
            payment_status: cleanStatus(s.payment_status),
            melava_status: cleanStatus(s.melava_status),
            image_url: safeUrl(apiMediaUrl(s.image_url)),
            payment_details: s.payment_details || {},
            created_at: s.created_at || null,
            melava_id: s.melava_id,
          })),
        );
      })
      .catch((e) => { if (!cancelled) setLoadError(e.message); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [id, idOk, reloadKey, canViewPayments]);

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
    if (!confirm || verifyingId || !canApprovePayments) return;
    const { item } = confirm;
    setVerifyingId(`${item.type}:${item.id}`);
    try {
      await verifyPayment(id, item.id, item.type, item.melava_id);
      setShots((list) => list.map((s) => (s.id === item.id && s.type === item.type
        ? {
          ...s,
          status: "verified",
          payment_status: "verified",
          melava_status: "verified",
          payment_details: { ...s.payment_details, verification_status: "verified" },
        }
        : s)));
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

  const resetPaymentDetails = () => setPaymentDetails({
    category: "profile",
    amount: "",
    payment_date: todayIsoDate(),
    payment_method: "",
    reference_number: "",
    remarks: "",
  });

  const onPick = (e) => {
    const picked = e.target.files?.[0];
    clearFile();
    if (!picked) return;
    if (!ALLOWED_TYPES.includes(picked.type) && !/\.(?:jpe?g|png|webp|pdf)$/i.test(picked.name)) {
      setFileError("Please choose a JPG, PNG, WEBP or PDF file.");
      return;
    }
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
      await uploadScreenshot(id, file, paymentDetails);
      if (BASE) {
        reload();                                                   // real backend: fetch the new list
      } else {                                                      // sample mode: show it locally
        setShots((list) => [...list, {
          id: Date.now(),
          type: "payment",
          status: "pending",
          image_url: URL.createObjectURL(file),
          payment_details: { ...paymentDetails, verification_status: "pending" },
        }]);
      }
      clearFile();
      resetPaymentDetails();
      showToast("success", "Payment entry added. It needs to be verified.");
    } catch (err) {
      showToast("error", err.message);
    } finally {
      setUploading(false);
    }
  };

  /* ---- what to show in the top badges ---- */
  const paymentShots = shots.filter((s) => s.type === "payment" || s.type === "combined" || s.type === "booklet");
  const melavaShot = shots.find((s) => s.type === "melava" || s.type === "combined" || s.type === "booklet");
  const paymentVerified = paymentShots.some((s) => (s.payment_status || s.status) === "verified");
  const melavaVerified = melavaShot
    ? (melavaShot.melava_status || melavaShot.status) === "verified"
    : false;
  let n = 0;                                                         // numbers the normal screenshots: #1, #2 …

  return (
    <AdminLayout currentPath="/admin/profiles">
      <div className="ps-page">
        {/* ---------- Title ---------- */}
        <div className="ps-top">
          <div>
            <h1>Payment History</h1>
                <p>Track every candidate payment entry, review proof, and verify payments.</p>
          </div>
          <a href={LINKS.back} className="ps-btn ps-btn-teal"><ArrowLeft size={16} /> Back to Profiles</a>
        </div>

        {!canViewPayments ? (
          <div className="ps-card ps-state">
            <AlertTriangle size={34} />
            <p>You do not have permission to view payments.</p>
            <a href={LINKS.back} className="ps-btn ps-btn-teal"><ArrowLeft size={16} /> Back to Profiles</a>
          </div>
        ) : error ? (
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
                    <h2>{profile.full_name || "Unnamed profile"}</h2>
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
                      <span className={`ps-big ${melavaVerified ? "ok" : "wait"}`}>
                        {melavaVerified ? <BadgeCheck size={18} /> : <Clock size={18} />}
                        Melava Registration {melavaVerified ? "Verified" : "Pending"}
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
                const title = s.type === "melava"
                  ? "Melava Registration Screenshot"
                  : s.type === "booklet"
                    ? "Booklet Payment Screenshot"
                  : s.type === "combined"
                    ? "Shared Payment Screenshot"
                    : `Payment Entry #${n}`;
                return (
                  <Screenshot
                    key={`${s.type}-${s.id}`}
                    item={s}
                    title={title}
                    busy={verifyingId === `${s.type}:${s.id}`}
                    onVerify={(item, t) => setConfirm({ item, title: t })}
                    onOpen={setViewer}
                    canVerify={canApprovePayments}
                  />
                );
              })}
            </div>

            {/* ---------- Upload another screenshot ---------- */}
            {!loading && canCreatePayments && (
              <form className="ps-card ps-upload" onSubmit={doUpload}>
                <h3>Add Payment Entry</h3>
                <p>Add another payment record for this candidate. Entries are saved individually and await verification.</p>

                <div className="ps-entry-fields">
                  <label>
                    <span>Payment category</span>
                    <select value={paymentDetails.category} onChange={(event) => setPaymentDetails((current) => ({ ...current, category: event.target.value }))} disabled={uploading}>
                      <option value="profile">Profile payment</option>
                      <option value="melava">Melava registration</option>
                      <option value="booklet">Booklet</option>
                      <option value="combined">Melava + booklet</option>
                    </select>
                  </label>
                  <label>
                    <span>Amount</span>
                    <input type="number" min="0" step="0.01" value={paymentDetails.amount} onChange={(event) => setPaymentDetails((current) => ({ ...current, amount: event.target.value }))} placeholder="Enter amount" disabled={uploading} />
                  </label>
                  <label>
                    <span>Payment date</span>
                    <input type="date" value={paymentDetails.payment_date} onChange={(event) => setPaymentDetails((current) => ({ ...current, payment_date: event.target.value }))} disabled={uploading} />
                  </label>
                  <label>
                    <span>Payment method</span>
                    <input value={paymentDetails.payment_method} onChange={(event) => setPaymentDetails((current) => ({ ...current, payment_method: event.target.value }))} placeholder="Cash, UPI, bank transfer..." disabled={uploading} />
                  </label>
                  <label>
                    <span>Transaction/reference number</span>
                    <input value={paymentDetails.reference_number} onChange={(event) => setPaymentDetails((current) => ({ ...current, reference_number: event.target.value }))} placeholder="Optional" disabled={uploading} />
                  </label>
                  <label className="ps-entry-remarks">
                    <span>Remarks</span>
                    <textarea value={paymentDetails.remarks} onChange={(event) => setPaymentDetails((current) => ({ ...current, remarks: event.target.value }))} rows={2} placeholder="Optional notes" disabled={uploading} />
                  </label>
                </div>

                <label className={`ps-drop ${file ? "has-file" : ""} ${fileError ? "has-error" : ""}`}>
                  <input ref={fileInput} type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onChange={onPick} disabled={uploading} />
                  {preview && !/\.pdf$/i.test(file?.name || "") ? (
                    <img src={preview} alt="Selected screenshot preview" className="ps-preview" />
                  ) : (
                    file && /\.pdf$/i.test(file.name) ? <FileText size={30} /> : <UploadCloud size={30} />
                  )}
                  <span className="ps-drop-text">
                    {file ? <><strong>{file.name}</strong><small>{formatSize(file.size)}</small></> : <><strong>Choose proof file</strong><small>JPG, PNG, WEBP or PDF · up to {MAX_FILE_MB} MB</small></>}
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
        {confirm && canApprovePayments && (
          <div className="ps-overlay" onMouseDown={(e) => { if (e.target === e.currentTarget && !verifyingId) setConfirm(null); }}>
            <div className="ps-modal" role="dialog" aria-modal="true" aria-labelledby="ps-confirm-title">
              <div className="ps-modal-icon"><BadgeCheck size={30} /></div>
              <h3 id="ps-confirm-title">Verify this payment?</h3>
              <p>
                You are about to mark <strong>{confirm.title}</strong> as verified.
                {confirm.item.type === "combined"
                  ? " This approves both the profile payment and Melava registration payment."
                  : " Please make sure the amount and the name in the screenshot are correct."}
              </p>
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