import { useCallback, useEffect, useRef, useState } from "react";
import {
  CalendarDays, CheckCircle2, Clock3, Download, Eye, FileText, IndianRupee, MapPin, Phone,
  ReceiptText, ShieldCheck, UserRound, UsersRound, X,
} from "lucide-react";
import { EmptyState, ErrorState, PageHeader, Skeleton, StatusBadge } from "../components/Ui";
import CandidateLayout from "../components/CandidateLayout";
import { getCachedHomeData } from "../../auth";
import { getCandidateMelavaRegistrations } from "../api";

const receiptOrg = {
  name: "AARYANS MANOMILAN",
  phone: "7030932526",
  website: "www.aaryansmanomilan.com",
  logo: "/website/Mano_Milan_Final_Website.png",
  qr: "/website/Manomilan-QR.jpeg",
};

const formatDate = (value, fallback = "Not available") => {
  if (!value) return fallback;
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? fallback
    : date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

const formatAmount = (amount, currency = "INR") =>
  new Intl.NumberFormat("en-IN", { style: "currency", currency }).format(Number(amount) || 0);

function ReceiptCard({ registration }) {
  const [downloading, setDownloading] = useState(false);
  const [downloadError, setDownloadError] = useState("");
  const [previewOpen, setPreviewOpen] = useState(false);
  const paid = registration.payment_status === "paid";
  const paperRef = useRef(null);

  const downloadReceipt = async () => {
    if (!paperRef.current || downloading) return;
    setDownloading(true);
    setDownloadError("");
    try {
      const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
        import("html2canvas"),
        import("jspdf"),
      ]);
      const images = Array.from(paperRef.current.querySelectorAll("img"));
      await Promise.all(images.map((image) => (
        image.complete
          ? Promise.resolve()
          : new Promise((resolve) => {
            image.addEventListener("load", resolve, { once: true });
            image.addEventListener("error", resolve, { once: true });
          })
      )));
      const canvas = await html2canvas(paperRef.current, {
        scale: 2,
        useCORS: true,
        backgroundColor: "#ffffff",
        logging: false,
      });
      const doc = new jsPDF("p", "mm", "a4");
      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      let width = pageWidth;
      let height = (canvas.height * pageWidth) / canvas.width;
      if (height > pageHeight) {
        height = pageHeight;
        width = (canvas.width * pageHeight) / canvas.height;
      }
      doc.addImage(canvas.toDataURL("image/png"), "PNG", (pageWidth - width) / 2, 0, width, height);
      doc.save(`Melava_Receipt_${registration.registration_id}.pdf`);
    } catch (error) {
      console.error("Could not download Melava receipt:", error);
      setDownloadError("Could not download the receipt. Please try again.");
    } finally {
      setDownloading(false);
    }
  };

  return (
    <article className="cnd-receipt-card">
      <div className="cnd-receipt-banner cnd-receipt-banner-empty">
        <ReceiptText size={42} />
        <span className="cnd-receipt-event-tag"><ReceiptText size={13} /> Melava event</span>
      </div>
      <div className="cnd-receipt-body">
        <div className="cnd-receipt-title-row">
          <div>
            <h2>{registration.event_name}</h2>
            <span className="cnd-receipt-id">Registration ID: {registration.registration_id}</span>
          </div>
          <StatusBadge status={paid ? "accepted" : "pending"}>{paid ? "Paid" : "Payment pending"}</StatusBadge>
        </div>
        <div className="cnd-receipt-details">
          <div><CalendarDays size={15} /><span><small>Registered on</small><strong>{formatDate(registration.registered_at)}</strong></span></div>
          <div><Clock3 size={15} /><span><small>Event date</small><strong>{formatDate(registration.event_start_date)}</strong></span></div>
          <div><MapPin size={15} /><span><small>Venue</small><strong>{registration.venue || "Not available"}</strong></span></div>
          <div><ReceiptText size={15} /><span><small>Amount paid</small><strong>{formatAmount(registration.amount, registration.currency)}</strong></span></div>
        </div>
        <div className={`cnd-receipt-footer ${paid ? "" : "is-unavailable"}`}>
          <span className="cnd-receipt-status">
            {paid ? <CheckCircle2 size={15} /> : <FileText size={15} />}
            {paid ? "Receipt available" : registration.payment_submitted ? "Payment submitted; receipt pending verification" : "Receipt not available yet"}
          </span>
          {paid ? (
            <div className="cnd-receipt-actions">
              <button type="button" className="cnd-btn cnd-btn-ghost cnd-receipt-view" onClick={() => setPreviewOpen(true)}>
                <Eye size={16} /> View Receipt
              </button>
              <button type="button" className="cnd-btn cnd-btn-primary cnd-receipt-download" onClick={downloadReceipt} disabled={downloading}>
                <Download size={16} /> {downloading ? "Preparing..." : "Download Receipt"} <span className="cnd-pdf-label">PDF</span>
              </button>
            </div>
          ) : (
            <button type="button" className="cnd-btn cnd-btn-ghost cnd-receipt-download" disabled>
              <FileText size={16} /> Receipt Not Available
            </button>
          )}
        </div>
        {downloadError ? <p className="cnd-receipt-download-error" role="alert">{downloadError}</p> : null}
      </div>
      {paid ? (
        <div className={`candidate-receipt-export-wrap${previewOpen ? " is-preview" : ""}`} role={previewOpen ? "dialog" : undefined} aria-modal={previewOpen ? "true" : undefined} aria-label={previewOpen ? "Payment receipt preview" : undefined}>
          {previewOpen ? (
            <button type="button" className="candidate-receipt-preview-close" onClick={() => setPreviewOpen(false)} aria-label="Close receipt preview">
              <X size={20} />
            </button>
          ) : null}
          <div className="candidate-receipt-export" ref={paperRef}>
          <header className="candidate-receipt-export-head">
            <img src={receiptOrg.logo} alt="" />
            <div>
              <strong>Aaryans Manomilan</strong>
              <span>Melava Registration Receipt</span>
            </div>
          </header>
          <div className="candidate-receipt-paper-card">
            <main className="candidate-receipt-export-body">
              <div className="candidate-receipt-export-title">
                <h1>Payment Receipt</h1>
                <span>Receipt No. <strong>{registration.receipt_no}</strong></span>
              </div>
              <div className="candidate-receipt-export-rows">
                {[
                  [CalendarDays, "Receipt Date", registration.receipt_date || formatDate(new Date())],
                  [UserRound, "Candidate Name", registration.candidate_name || "—"],
                  [Phone, "Mobile Number", registration.mobile || "—"],
                  [UsersRound, "Profile For", registration.profile_for || "—"],
                  [MapPin, "City", registration.city || "—"],
                  [IndianRupee, "Amount Paid", formatAmount(registration.amount, registration.currency)],
                ].map(([Icon, label, value]) => (
                  <div key={label}><span className="candidate-receipt-row-icon"><Icon size={16} /></span><span>{label}</span><b>:</b><strong>{value}</strong></div>
                ))}
                <div>
                  <span className="candidate-receipt-row-icon"><ShieldCheck size={16} /></span>
                  <span>Payment Status</span>
                  <b>:</b>
                  <strong className="candidate-receipt-paid"><CheckCircle2 size={17} /> PAID &amp; VERIFIED</strong>
                </div>
              </div>
              <p className="candidate-receipt-thanks"><CheckCircle2 size={22} /> Thank you for registering with Aaryans Manomilan.</p>
              <p className="candidate-receipt-note">This is a computer generated receipt and does not require a physical signature.</p>
            </main>
            <footer className="candidate-receipt-export-foot">
              <img src={receiptOrg.logo} alt="" />
              <div><strong>{receiptOrg.name}</strong><span>{receiptOrg.phone} &nbsp;|&nbsp; {receiptOrg.website}</span></div>
              <img src={receiptOrg.qr} alt="" />
            </footer>
          </div>
          {previewOpen ? (
            <div className="candidate-receipt-preview-actions">
              <button type="button" className="cnd-btn cnd-btn-primary" onClick={downloadReceipt} disabled={downloading}>
                <Download size={16} /> {downloading ? "Preparing..." : "Download Receipt"} <span className="cnd-pdf-label">PDF</span>
              </button>
            </div>
          ) : null}
        </div>
      </div>
      ) : null}
    </article>
  );
}

export default function MelavaReceipts() {
  const cached = getCachedHomeData();
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const candidateInfo = {
    name: cached?.profile?.full_name || cached?.user?.name || cached?.user?.username,
    id: cached?.profile?.registration_number || cached?.profile?.profile_id || cached?.user?.username,
    photo: cached?.profile?.photo_url || "",
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setRegistrations(await getCandidateMelavaRegistrations());
    } catch (loadError) {
      setError(loadError.message || "Could not load Melava registrations.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    Promise.resolve().then(load);
  }, [load]);

  return (
    <CandidateLayout currentPath="/candidate/melava-receipts" candidateInfo={candidateInfo}>
      <PageHeader
        eyebrow="Payments & registrations"
        title="Melava Receipts"
        subtitle="View your registered Melava events and download your payment receipts."
        actions={<span className="cnd-receipt-header-art" aria-hidden="true"><ReceiptText size={29} /></span>}
      />
      {loading ? (
        <div className="cnd-receipt-list">
          {[0, 1].map((item) => <div key={item} className="cnd-card"><Skeleton lines={5} /></div>)}
        </div>
      ) : error ? (
        <ErrorState message={error} onRetry={load} />
      ) : registrations.length === 0 ? (
        <section className="cnd-card cnd-receipts-empty-card">
          <EmptyState icon={ReceiptText} title="Melava not available" message="There are no Melava registrations or payment receipts available at the moment." />
        </section>
      ) : (
        <section className="cnd-receipt-list" aria-label="Registered Melava events">
          {registrations.map((registration) => <ReceiptCard key={registration.id} registration={registration} />)}
        </section>
      )}
    </CandidateLayout>
  );
}
