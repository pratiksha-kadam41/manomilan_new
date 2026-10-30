import { useRef, useState } from "react";
import { Download, ArrowLeft, X } from "lucide-react";
import "./BioDataModal.css";

const TEMPLATES = [
  { id: "garden", name: "Garden", description: "Fresh floral details", className: "bd-theme-garden" },
  { id: "lotus", name: "Lotus", description: "Soft rose and plum", className: "bd-theme-lotus" },
  { id: "classic", name: "Classic", description: "Elegant ivory and gold", className: "bd-theme-classic" },
  { id: "marigold", name: "Marigold", description: "Warm festive colors", className: "bd-theme-marigold" },
  { id: "royal", name: "Royal", description: "Traditional gold detail", className: "bd-theme-royal" },
];

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
  const age = Math.max(0, Math.floor((Date.now() - date.getTime()) / 31557600000));
  return `${date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })} (Age: ${age})`;
};

function Flower({ x, y, rotation = 0 }) {
  return (
    <g transform={`translate(${x} ${y}) rotate(${rotation})`}>
      {[0, 72, 144, 216, 288].map((angle) => (
        <ellipse key={angle} cx="0" cy="-10" rx="5.5" ry="11" transform={`rotate(${angle})`}
          fill="var(--bd-wash)" stroke="currentColor" strokeWidth="1.6" />
      ))}
      <circle r="5" fill="#e69a36" stroke="currentColor" strokeWidth="1.3" />
    </g>
  );
}

function Leaf({ x, y, rotation = 0 }) {
  return (
    <g transform={`translate(${x} ${y}) rotate(${rotation})`}>
      <path d="M0 0 C-14 -3 -17 -14 -16 -18 C-5 -18 1 -11 0 0Z" fill="var(--bd-wash)" stroke="currentColor" strokeWidth="1.5" />
      <path d="M0 0 L-13 -15" fill="none" stroke="currentColor" strokeWidth="1" />
    </g>
  );
}

function FloralFrame() {
  return (
    <svg className="bd-ornamental-frame" viewBox="0 0 700 1000" preserveAspectRatio="none" aria-hidden="true">
      <rect x="14" y="14" width="672" height="972" rx="12" fill="none" stroke="currentColor" strokeWidth="2.5" />
      <rect x="21" y="21" width="658" height="958" rx="9" fill="none" stroke="currentColor" strokeWidth="1" opacity=".65" />
      <path d="M38 138 C78 119 92 90 61 46 M662 138 C622 119 608 90 639 46 M38 862 C78 881 92 910 61 954 M662 862 C622 881 608 910 639 954"
        fill="none" stroke="currentColor" strokeWidth="2" />
      <path d="M38 220 C56 274 28 318 39 362 M662 220 C644 274 672 318 661 362 M38 780 C56 726 28 682 39 638 M662 780 C644 726 672 682 661 638"
        fill="none" stroke="currentColor" strokeWidth="1.7" />
      <Flower x={52} y={52} />
      <Flower x={648} y={52} rotation={72} />
      <Flower x={52} y={948} rotation={36} />
      <Flower x={648} y={948} rotation={108} />
      <Flower x={49} y={385} rotation={25} />
      <Flower x={651} y={615} rotation={58} />
      <Leaf x={70} y={86} rotation={35} />
      <Leaf x={88} y={111} rotation={210} />
      <Leaf x={630} y={86} rotation={145} />
      <Leaf x={612} y={111} rotation={330} />
      <Leaf x={48} y={180} rotation={35} />
      <Leaf x={51} y={252} rotation={210} />
      <Leaf x={47} y={320} rotation={35} />
      <Leaf x={652} y={680} rotation={145} />
      <Leaf x={649} y={748} rotation={330} />
      <Leaf x={652} y={820} rotation={145} />
      <Leaf x={70} y={914} rotation={145} />
      <Leaf x={88} y={889} rotation={330} />
      <Leaf x={630} y={914} rotation={35} />
      <Leaf x={612} y={889} rotation={210} />
      <path d="M92 52 C136 37 174 39 210 52 M490 52 C526 39 564 37 608 52 M92 948 C136 963 174 961 210 948 M490 948 C526 961 564 963 608 948"
        fill="none" stroke="currentColor" strokeWidth="1.7" />
      <Flower x={226} y={52} rotation={24} />
      <Flower x={474} y={52} rotation={78} />
      <Flower x={226} y={948} rotation={64} />
      <Flower x={474} y={948} rotation={12} />
    </svg>
  );
}

function Butterfly() {
  return (
    <svg className="bd-butterfly" viewBox="0 0 64 52" aria-hidden="true">
      <path d="M31 25 C22 5 5 1 4 12 C3 22 17 28 29 30 C16 31 12 41 19 45 C26 48 31 38 32 30Z"
        fill="#e7a43c" stroke="#985a30" strokeWidth="1.5" />
      <path d="M33 25 C42 5 59 1 60 12 C61 22 47 28 35 30 C48 31 52 41 45 45 C38 48 33 38 32 30Z"
        fill="#efb74c" stroke="#985a30" strokeWidth="1.5" />
      <path d="M32 15 C29 9 23 5 18 5 M32 15 C35 9 41 5 46 5" fill="none" stroke="#985a30" strokeWidth="1.4" />
      <ellipse cx="32" cy="29" rx="2.5" ry="13" fill="#6c4735" />
    </svg>
  );
}

function BioDataPaper({ profile, template, paperRef }) {
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
    ["Monthly Income", profile.monthly_income ? `₹${Number(profile.monthly_income).toLocaleString("en-IN")}` : "N/A"],
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
    <article ref={paperRef} className={`bd-paper ${template.className}`}>
      <div className="bd-frame">
        <FloralFrame />
        <header className="bd-paper-header">
          <img src={ORG.logo} alt="Aaryans Manomilan" />
          <div className="bd-header-center"><Butterfly /><div className="bd-jai">॥ जय जिनेन्द्र ॥</div></div>
          <span className="bd-header-space" aria-hidden="true" />
        </header>

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

export default function BioDataModal({ profile, onClose }) {
  const [selected, setSelected] = useState(null);
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
      const canvas = await html2canvas(paperRef.current, {
        scale: 2,
        useCORS: true,
        backgroundColor: "#ffffff",
      });
      const pdf = new jsPDF("p", "mm", "a4");
      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();
      const pagePixelHeight = Math.floor((canvas.width * pageHeight) / pageWidth);

      for (let page = 0, top = 0; top < canvas.height; page++, top += pagePixelHeight) {
        const slice = document.createElement("canvas");
        slice.width = canvas.width;
        slice.height = Math.min(pagePixelHeight, canvas.height - top);
        slice.getContext("2d").drawImage(
          canvas,
          0, top, canvas.width, slice.height,
          0, 0, slice.width, slice.height,
        );
        if (page > 0) pdf.addPage();
        const imageHeight = (slice.height * pageWidth) / slice.width;
        pdf.addImage(slice.toDataURL("image/png"), "PNG", 0, 0, pageWidth, imageHeight);
      }

      const safeName = display(profile.full_name).replace(/[^\w-]+/g, "_");
      pdf.save(`BioData_${safeName}.pdf`);
    } catch (downloadError) {
      console.error("Could not create biodata PDF:", downloadError);
      setError("Could not create the PDF. Please try again or use your browser's Print option.");
    } finally {
      setCreatingPdf(false);
    }
  };

  return (
    <div className="bd-overlay" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget) onClose();
    }}>
      <section className={`bd-dialog${selected ? " bd-dialog-preview" : ""}`} role="dialog" aria-modal="true"
        aria-labelledby="bd-dialog-title">
        <header className="bd-dialog-head">
          {selected && <button className="bd-back" type="button" onClick={() => { setSelected(null); setError(""); }}>
            <ArrowLeft size={17} /> Back to Templates
          </button>}
          <h2 id="bd-dialog-title">{selected ? "Bio-Data Preview" : "Select a Template"}</h2>
          <button type="button" className="bd-close" aria-label="Close biodata preview" onClick={onClose}><X size={22} /></button>
        </header>

        {!selected ? (
          <div className="bd-template-grid">
            {TEMPLATES.map((template) => (
              <button type="button" className="bd-template-option" key={template.id} onClick={() => setSelected(template)}>
                <div className={`bd-template-sample ${template.className}`} aria-hidden="true">
                  <FloralFrame />
                  <span className="bd-sample-logo">Aaryans<br />Manomilan</span>
                  <span className="bd-sample-title">॥ जय जिनेन्द्र ॥</span>
                  <span className="bd-sample-lines"><i /><i /><i /><i /><i /></span>
                </div>
                <span className="bd-template-label">{template.name}</span>
              </button>
            ))}
          </div>
        ) : (
          <>
            <div className="bd-preview-scroll">
              <BioDataPaper profile={profile} template={selected} paperRef={paperRef} />
            </div>
            {error && <p className="bd-error" role="alert">{error}</p>}
            <footer className="bd-actions">
              <button type="button" className="bd-btn bd-btn-back" onClick={() => { setSelected(null); setError(""); }}>Back to Templates</button>
              <button type="button" className="bd-btn bd-btn-download" onClick={downloadPdf} disabled={creatingPdf}>
                <Download size={17} /> {creatingPdf ? "Creating PDF…" : "Download as PDF"}
              </button>
            </footer>
          </>
        )}
      </section>
    </div>
  );
}
