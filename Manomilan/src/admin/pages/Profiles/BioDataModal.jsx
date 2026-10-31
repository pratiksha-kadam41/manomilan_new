import { useRef, useState } from "react";
import { Download, ExternalLink, X } from "lucide-react";
import { BIODATA_TEMPLATES } from "./biodataTemplates.js";
import "./BioDataModal.css";

const ORG = {
  logo: "/website/Mano_Milan_Final_Website.png",
  qr: "/website/Manomilan-QR.jpeg",
  phone: "7030932526",
  website: "www.aaryansmanomilan.com",
};

const display = (value) => {
  if (value === null || value === undefined || String(value).trim() === "") return "N/A";
  return String(value).trim();
};

const dateText = (value) => {
  if (!value) return "N/A";
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  if (Number.isNaN(date.getTime())) return display(value);
  const today = new Date();
  let age = today.getFullYear() - date.getFullYear();
  if (
    today.getMonth() < date.getMonth()
    || (today.getMonth() === date.getMonth() && today.getDate() < date.getDate())
  ) age -= 1;
  return `${date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })} (Age: ${age})`;
};

function BioDataPaper({ profile, template, orientation, paperRef }) {
  const personal = [
    ["Date of Birth", dateText(profile.dob)],
    ["Mobile No", display(profile.mobile)],
    ["Height", display(profile.height)],
    ["Caste", display(profile.caste)],
    ["Sub Caste", display(profile.sub_caste)],
    ["Home Town", display(profile.home_town)],
    ["Current City", display(profile.current_city)],
    ["Education", display(profile.education)],
    ["Occupation", display(profile.profession)],
    ["Monthly Income", Number.isFinite(Number(profile.monthly_income)) && profile.monthly_income !== null && profile.monthly_income !== ""
      ? `₹${Number(profile.monthly_income).toLocaleString("en-IN")}` : display(profile.monthly_income)],
    ["Hobbies", display(profile.hobbies)],
  ];
  const siblingCount = [profile.brothers, profile.sisters]
    .filter((count) => count !== null && count !== undefined && String(count).trim() !== "")
    .map(display)
    .join(" / ");
  const family = [
    ["Father's Name", profile.father_name],
    ["Father's Occupation", profile.father_occupation],
    ["Mother's Name", profile.mother_name],
    ["Mother's Occupation", profile.mother_occupation],
    ["Brother/Sister", siblingCount || "N/A"],
  ];
  const expectations = [
    ["Preferred Caste", profile.pref_caste],
    ["Preferred Age Range", profile.pref_age_range],
    ["Other Expectations", profile.other_expectations],
  ];

  return (
    <article
      ref={paperRef}
      className={`bd-paper bd-paper-${orientation}`}
      style={{ backgroundImage: `url(${template.image})`, "--bd-ink": template.ink }}
    >
      <div className="bd-frame" style={{ padding: template.safe }}>
        {template.showHeader && (
          <header className="bd-paper-header">
            <img src={ORG.logo} alt="Aaryans Manomilan" />
          </header>
        )}

        <h1>{display(profile.full_name)}</h1>
        <section className="bd-personal">
          <h2>Personal Details</h2>
          <div className="bd-person-layout">
            <dl className="bd-details">
              {personal.map(([label, value]) => (
                <div className="bd-detail" key={label}>
                  <dt>{label}</dt><dd>{display(value)}</dd>
                </div>
              ))}
            </dl>
            {profile.photo_url && (
              <img className="bd-photo" src={profile.photo_url} alt={`${display(profile.full_name)} profile`} crossOrigin="anonymous" />
            )}
          </div>
        </section>

        <div className="bd-lower-grid">
          <section>
            <h2>Family Details</h2>
            <dl className="bd-details bd-compact">
              {family.map(([label, value]) => (
                <div className="bd-detail" key={label}>
                  <dt>{label}</dt><dd>{display(value)}</dd>
                </div>
              ))}
            </dl>
          </section>
          <section>
            <h2>Expectations</h2>
            <dl className="bd-details bd-compact">
              {expectations.map(([label, value]) => (
                <div className="bd-detail" key={label}>
                  <dt>{label}</dt><dd>{display(value)}</dd>
                </div>
              ))}
            </dl>
          </section>
        </div>

        <footer className="bd-paper-footer">
          <img src={ORG.qr} alt="Aaryans Manomilan QR code" crossOrigin="anonymous" />
          <div><strong>Aaryans Manomilan</strong><span>{ORG.phone} | {ORG.website}</span></div>
        </footer>
      </div>
    </article>
  );
}

export default function BioDataModal({
  profile,
  onClose,
  onOpenFullScreen,
  initialTemplateId = "",
  initialOrientation = "portrait",
  fullScreen = false,
  loading = false,
  loadError = "",
}) {
  const [selected, setSelected] = useState(
    () => BIODATA_TEMPLATES.find((template) => template.id === String(initialTemplateId)) || null,
  );
  const [orientation, setOrientation] = useState(initialOrientation);
  const [creatingPdf, setCreatingPdf] = useState(false);
  const [error, setError] = useState("");
  const paperRef = useRef(null);

  const downloadPdf = async () => {
    if (!paperRef.current || creatingPdf) return;
    setCreatingPdf(true);
    setError("");

    try {
      const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
        import("html2canvas"),
        import("jspdf"),
      ]);
      await document.fonts.ready;
      const images = Array.from(paperRef.current.querySelectorAll("img"));
      await Promise.all(images.map((image) => image.decode().catch(() => undefined)));

      const canvas = await html2canvas(paperRef.current, {
        scale: 2,
        useCORS: true,
        allowTaint: false,
        imageTimeout: 20000,
        backgroundColor: "#ffffff",
      });
      if (!canvas.width || !canvas.height) {
        throw new Error("The biodata preview rendered as an empty image.");
      }

      const pdf = new jsPDF(orientation === "landscape" ? "l" : "p", "mm", "a4");
      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();
      pdf.addImage(canvas.toDataURL("image/jpeg", 0.92), "JPEG", 0, 0, pageWidth, pageHeight);

      const safeName = display(profile.full_name).replace(/[^\w-]+/g, "_");
      const blob = pdf.output("blob");
      if (blob.size < 5 || blob.type !== "application/pdf") {
        throw new Error("The PDF could not be created from the preview.");
      }

      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `BioData_${safeName || "Candidate"}.pdf`;
      link.style.display = "none";
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (downloadError) {
      console.error("Could not create biodata PDF:", downloadError);
      setError(
        downloadError instanceof Error
          ? `Could not create the PDF: ${downloadError.message}`
          : "Could not create the PDF. Please try again.",
      );
    } finally {
      setCreatingPdf(false);
    }
  };

  return (
    <div className={`bd-overlay${fullScreen ? " bd-overlay-fullscreen" : ""}`} role="presentation" onMouseDown={(event) => {
      if (!fullScreen && event.target === event.currentTarget) onClose();
    }}>
      <section className={`bd-dialog${selected ? " bd-dialog-preview" : ""}${fullScreen ? " bd-dialog-fullscreen" : ""}`} role="dialog" aria-modal="true"
        aria-labelledby="bd-dialog-title">
        <header className="bd-dialog-head">
          <h2 id="bd-dialog-title">{selected ? "Bio-Data Preview" : "Select a Template"}</h2>
          <button
            type="button"
            className="bd-close"
            aria-label={fullScreen ? "Close full-page biodata" : "Close biodata preview"}
            onClick={onClose}
          >
            <X size={22} />
          </button>
        </header>

        {!selected ? (
          <div className="bd-template-grid">
            {BIODATA_TEMPLATES.map((template) => (
              <button type="button" className="bd-template-option" key={template.id}
                aria-label={`Select ${template.name} template`} onClick={() => setSelected(template)}>
                <img className="bd-template-thumb" src={template.image} alt={template.name} />
              </button>
            ))}
          </div>
        ) : (
          <>
            <div className="bd-orientation" role="group" aria-label="Biodata page orientation">
              <span>Orientation</span>
              <button
                type="button"
                className="bd-orientation-option"
                aria-pressed={orientation === "portrait"}
                onClick={() => setOrientation("portrait")}
              >
                Vertical
              </button>
              <button
                type="button"
                className="bd-orientation-option"
                aria-pressed={orientation === "landscape"}
                onClick={() => setOrientation("landscape")}
              >
                Horizontal
              </button>
            </div>
            <div className="bd-preview-scroll">
              {loading ? (
                <div className="bd-load-state" role="status">
                  <span className="bd-loader" />
                  <strong>Preparing candidate details…</strong>
                  <span>You can choose the template while this loads.</span>
                </div>
              ) : loadError ? (
                <div className="bd-load-error" role="alert">{loadError}</div>
              ) : profile ? (
                <BioDataPaper
                  profile={profile}
                  template={selected}
                  orientation={orientation}
                  paperRef={paperRef}
                />
              ) : (
                <div className="bd-load-error" role="alert">Candidate BioData is unavailable.</div>
              )}
            </div>
            {error && <p className="bd-error" role="alert">{error}</p>}
            <footer className="bd-actions">
              <button type="button" className="bd-btn bd-btn-back" onClick={() => { setSelected(null); setError(""); }}>Back to Templates</button>
              {onOpenFullScreen && profile && !loading && !loadError && (
                <button
                  type="button"
                  className="bd-btn bd-btn-fullscreen"
                  onClick={() => onOpenFullScreen({ template: selected, orientation })}
                >
                  <ExternalLink size={17} /> Open Full Page
                </button>
              )}
              <button
                type="button"
                className="bd-btn bd-btn-download"
                onClick={downloadPdf}
                disabled={creatingPdf || loading || Boolean(loadError) || !profile}
              >
                <Download size={17} /> {creatingPdf ? "Creating PDF…" : "Download as PDF"}
              </button>
            </footer>
          </>
        )}
      </section>
    </div>
  );
}
