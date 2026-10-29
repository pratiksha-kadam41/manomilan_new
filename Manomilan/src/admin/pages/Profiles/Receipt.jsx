import { useEffect, useRef, useState } from "react";
import { ArrowLeft, Printer, FileDown, Loader2, AlertTriangle } from "lucide-react";
import { apiUrl } from "../../../api";
import { getSession, clearSession } from "../../../auth";
import "./receipt.css";

// GET /profiles/:id/receipt returns a verified receipt; amounts stay empty
// until fee amounts are stored by the backend.
const LINKS = { back: "/admin/profiles" };

// Footer details printed on every receipt
const ORG = {
  name: "AARYANS MANOMILAN",
  phone: "7030932526",
  website: "www.aaryansmanomilan.com",
  logo: "/website/Mano_Milan_Final_Website.png",   // already in your project: public/website/
  qr: "/website/Manomilan-QR.jpeg",                // copy this file into public/website/ (see the steps)
};

const authHeaders = () => {
  const s = getSession();
  return { Accept: "application/json", ...(s ? { Authorization: `Bearer ${s.token}` } : {}) };
};

const guard = (res) => {
  if (res.status === 401) {
    clearSession();
    window.location.assign("/login?expired=1");
    throw new Error("Session expired.");
  }
  if (res.status === 404) throw new Error("This receipt was not found.");
  if (!res.ok) throw new Error("Server error. Please try again.");
  return res;
};

const isValidId = (id) => /^\d{1,12}$/.test(String(id ?? ""));      // only whole numbers are accepted as an id

async function getReceipt(id) {
  const response = guard(await fetch(
    `${apiUrl}/profiles/${encodeURIComponent(id)}/receipt`,
    { headers: authHeaders() },
  ));
  const body = await response.json();
  if (!body?.success || !body?.data) {
    throw new Error(body?.message || "Could not load receipt.");
  }
  return body.data;
}

/* ---------- Helpers ---------- */
const text = (v) => (v === null || v === undefined || v === "" ? "—" : String(v));

function amountText(r) {
  if (r.registration_fee == null && r.booklet_fee == null && r.total == null) {
    return "Not recorded";
  }
  const fee = Number(r.registration_fee) || 0;
  const booklet = Number(r.booklet_fee) || 0;
  const total = Number(r.total) || fee + booklet;
  return booklet > 0 ? `${fee} + ${booklet} (Booklet) = ${total} Rs/-` : `${total} Rs/-`;
}

const fileSafe = (s) => String(s || "").replace(/[^a-zA-Z0-9]+/g, "_").replace(/^_+|_+$/g, "").slice(0, 60);

/* ====================================================================
   2. THE PAGE
   ==================================================================== */
export default function Receipt({ params }) {
  const id = params?.id;
  const idOk = isValidId(id);

  const [receipt, setReceipt] = useState(null);
  const [loading, setLoading] = useState(idOk);
  const [loadError, setLoadError] = useState("");
  const error = idOk ? loadError : "This receipt link is not valid.";
  const [reloadKey, setReloadKey] = useState(0);
  const [making, setMaking] = useState(false);
  const [pdfError, setPdfError] = useState("");
  const paper = useRef(null);

  useEffect(() => {
    if (!idOk) return;
    let cancelled = false;
    getReceipt(id)
      .then((data) => { if (!cancelled) setReceipt(data); })
      .catch((e) => { if (!cancelled) setLoadError(e.message); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [id, idOk, reloadKey]);

  const retry = () => { setLoadError(""); setLoading(true); setReloadKey((k) => k + 1); };

  /* ---- Download as PDF (the tools are only loaded when this button is clicked) ---- */
  const downloadPdf = async () => {
    if (!paper.current || making) return;
    setMaking(true);
    setPdfError("");
    try {
      const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import("html2canvas"), import("jspdf")]);
      const canvas = await html2canvas(paper.current, { scale: 2, useCORS: true, backgroundColor: "#ffffff" });
      const pdf = new jsPDF("p", "mm", "a4");
      const pageW = pdf.internal.pageSize.getWidth();
      const pageH = pdf.internal.pageSize.getHeight();
      let w = pageW;
      let h = (canvas.height * pageW) / canvas.width;
      if (h > pageH) { h = pageH; w = (canvas.width * pageH) / canvas.height; }   // never run off the page
      pdf.addImage(canvas.toDataURL("image/png"), "PNG", (pageW - w) / 2, 0, w, h);
      pdf.save(`Receipt_${fileSafe(receipt?.candidate_name) || "Candidate"}.pdf`);
    } catch {
      setPdfError("Could not create the PDF. Please try again, or use Print and choose “Save as PDF”.");
    } finally {
      setMaking(false);
    }
  };

  const paid = receipt?.payment_status === "verified";

  return (
    <div className="rc-page">
      {/* ---------- Buttons (not printed) ---------- */}
      <div className="rc-bar">
        <a href={LINKS.back} className="rc-btn rc-btn-ghost"><ArrowLeft size={16} /> Back to Profiles</a>
        <div className="rc-bar-right">
          <button type="button" className="rc-btn rc-btn-ghost" onClick={() => window.print()} disabled={!receipt}>
            <Printer size={16} /> Print
          </button>
          <button type="button" className="rc-btn rc-btn-red" onClick={downloadPdf} disabled={!receipt || making}>
            {making ? <><Loader2 size={16} className="rc-spin" /> Creating PDF…</> : <><FileDown size={16} /> Download Receipt PDF</>}
          </button>
        </div>
      </div>
      {pdfError && <p className="rc-pdf-error" role="alert">{pdfError}</p>}

      {error ? (
        <div className="rc-state">
          <AlertTriangle size={34} />
          <p>{error}</p>
          {idOk && <button type="button" className="rc-btn rc-btn-red" onClick={retry}>Try again</button>}
        </div>
      ) : loading || !receipt ? (
        <div className="rc-paper rc-loading"><span /><span /><span /></div>
      ) : (
        /* ---------- The receipt itself (this is what becomes the PDF) ---------- */
        <div className="rc-paper" ref={paper}>
          <header className="rc-head">
            <h1>PAYMENT RECEIPT</h1>
            <p>Aaryans Manomilan {text(receipt.registration_type)}</p>
          </header>

          <section className="rc-meta">
            <div><span>Receipt Date</span><strong>{text(receipt.receipt_date)}</strong></div>
            <div><span>Receipt No</span><strong>{text(receipt.receipt_no)}</strong></div>
          </section>

          <table className="rc-table">
            <thead>
              <tr><th colSpan={2}>{text(receipt.registration_type)} Details</th></tr>
            </thead>
            <tbody>
              <tr><td>Candidate Name</td><td>{text(receipt.candidate_name)}</td></tr>
              <tr><td>Mobile Number</td><td>{text(receipt.mobile)}</td></tr>
              <tr><td>Profile For</td><td>{text(receipt.profile_for)}</td></tr>
              <tr><td>City</td><td>{text(receipt.city)}</td></tr>
              <tr><td>Amount Paid</td><td>{amountText(receipt)}</td></tr>
              <tr>
                <td>Payment Status</td>
                <td><span className={`rc-status ${paid ? "paid" : "wait"}`}>{paid ? "PAID & VERIFIED" : "PAYMENT PENDING"}</span></td>
              </tr>
            </tbody>
          </table>

          <p className="rc-thanks">Thank you for registering with Aaryans Manomilan.</p>
          <p className="rc-note">This is a computer generated receipt and does not require a physical signature.</p>

          <footer className="rc-foot">
            <img src={ORG.logo} alt="Aaryans Manomilan logo" className="rc-logo" onError={(e) => { e.currentTarget.style.visibility = "hidden"; }} />
            <div className="rc-org">
              <strong>{ORG.name}</strong>
              <span>{ORG.phone} &nbsp;|&nbsp; {ORG.website}</span>
            </div>
            <img src={ORG.qr} alt="QR code" className="rc-qr" onError={(e) => { e.currentTarget.style.visibility = "hidden"; }} />
          </footer>
        </div>
      )}
    </div>
  );
}