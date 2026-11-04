import { useEffect, useState } from "react";
import { PRIVACY_INTRO, PRIVACY_SECTIONS } from "../data/privacy.js";

const ADDRESS =
  "Aachal Prime' Building, Above Hotel 'Shree Kateel', Next to Navale Bridge, New Pune-Satara Bypass Highway, Near Amrit Bloomfield, Narhe, Pune - 411041.";

const QUICK_LINKS = [
  { label: "About Manomilan", href: "/aboutus" },
  { label: "How It Works", href: "/how-it-works" },
  { label: "Our services and Feature", href: "/our-services" },
  { label: "Privacy Policy", modal: "privacy" },
  { label: "Payment Policy", href: "/refund-policy" },
  { label: "Terms and Conditions", href: "/terms-and-conditions" },
];

const COMMUNITY_LINKS = [
  { label: "Digambar", modal: "digambar" },
  { label: "Shwetambar", modal: "shwetambar" },
  { label: "FAQs", href: "/faq" },
  { label: "Events and Workshops", href: "/events-workshops" },
];

const DIGAMBAR = [
  "Dashahumbad (दशाहुंबड)", "Saitwal (सैतवाल)", "Chaturth (चतुर्थ)", "Pancham (पंचम)",
  "Dhakad (धाकड)", "Kambhoj (कांबोज)", "Kasar (कासार)", "Bagerwal (बागरवाल)",
  "Jaiswal (जैसवाल)", "Agarwal (अगरवाल)", "Bogar (बोगार)", "Porwar (परवार)",
  "Humbad (हुम्बड)", "Visa Humbad (वीसा हुम्बड)", "Khandelwal (खंडेलवाल)", "Bannore (बन्नोरे)",
];

const SHWETAMBAR = [
  "Oswal (ओसवाल)", "Porwal (पोरवाल)", "Shrimali (श्रीमाळी)", "Humad (हुमड)",
  "Modi (मोदी)", "Khandelwal (खांडेलवाल)", "Gadiya (गाडिया)", "Bagherwal (बाघेरवाल)",
  "Dasha Shrimal / Dasha Oswal (दशा श्रीमाळ)", "Visa Shrimali / Visa Oswal (वीसा ओसवाल / श्रीमाळी)",
  "Soni (सोनी)", "Lunka (लुंका)",
];

function Popup({ title, onClose, wide, children }) {
  return (
    <div className={`modal fade show d-block pop${wide ? " wide" : ""}`}
         onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className={`modal-dialog modal-dialog-centered${wide ? " modal-dialog-scrollable" : ""}`}>
        <div className="modal-content">
          <div className="pop-head">
            <h2>{title}</h2>
            <span onClick={onClose}>&times;</span>
          </div>
          {children}
        </div>
      </div>
    </div>
  );
}

function SubCasteList({ items }) {
  return (
    <div className="pop-body">
      <ol>{items.map((c) => <li key={c}>{c}</li>)}</ol>
    </div>
  );
}

function PrivacyBody() {
  return (
    <div className="pop-body policy modal-body">
      <p style={{ marginBottom: 20 }}>{PRIVACY_INTRO}</p>
      {PRIVACY_SECTIONS.map((s) => (
        <section key={s.title}>
          <h3>{s.title}</h3>
          <ul>{s.items.map((t) => <li key={t}>{t}</li>)}</ul>
        </section>
      ))}
      <section>
        <h3>9. Grievance Officer</h3>
        <p style={{ marginBottom: 10 }}>
          In accordance with Information Technology Act 2010 and rules made there under, the name and contact details of the Grievance Officer are provided below:
        </p>
        <div className="officer">
          <p><strong>Office Hours:</strong> 10:30 AM to 7:00 PM (Closed on Wednesday)</p>
          <p><strong>Office Address:</strong> Aaryans Manomilan - Corporate Office, Narhe</p>
          <p>{ADDRESS}</p>
          <p><strong>Email:</strong> aaryansmanomilan@gmail.com</p>
          <p><strong>Contact:</strong> 7030932526</p>
        </div>
      </section>
    </div>
  );
}

function LinkList({ title, links, onModal }) {
  return (
    <div className="mmf-col">
      <h4 className="foot-h">{title}</h4>
      <ul className="foot-list">
        {links.map((l) => (
          <li key={l.label}>
            {l.modal
              ? <button type="button" onClick={() => onModal(l.modal)}>{l.label}</button>
              : <a href={l.href}>{l.label}</a>}
          </li>
        ))}
      </ul>
    </div>
  );
}

export default function Footer() {
  const [modal, setModal] = useState(null);

  useEffect(() => { document.body.style.overflow = modal ? "hidden" : "auto"; }, [modal]);
  const close = () => setModal(null);

  return (
    <>
      <footer id="contact" className="site-footer site-footer--premium">
        <div className="mmf-blessing" aria-hidden="true">॥ जय जिनेन्द्र ॥</div>

        <div className="site-container">
          <div className="mmf-grid">
            <div className="mmf-brand">
              <a className="mmf-logo" href="/" aria-label="Aaryans Manomilan home">
                <img
                  src="/website/Mano_Milan_Final_Website.png"
                  alt="Aaryan's Manomilan Logo"
                  onError={(e) => {
                    e.currentTarget.onerror = null;
                    e.currentTarget.src = "https://placehold.co/150x48/DC143C/FFFFFF?text=Aaryan's+Manomilan";
                  }}
                />
              </a>
              <p className="mmf-name">Aaryans Manomilan</p>
              <p className="mmf-tagline">Jain Matrimonial</p>
              <p className="mmf-blurb">
                Connecting Jain families with trusted, value-led matchmaking across communities.
              </p>
              <div className="foot-social">
                <a href="https://www.facebook.com/share/19sczFNKnf/" aria-label="Facebook"><i className="bi bi-facebook" aria-hidden="true" /></a>
                <a href="https://www.instagram.com/aaryansmanomilan?igsh=Y3p0cGd4b3I3a284" aria-label="Instagram"><i className="bi bi-instagram" aria-hidden="true" /></a>
                <a href="https://www.linkedin.com/in/aaryans-manomilan-257615388/" aria-label="LinkedIn"><i className="bi bi-linkedin" aria-hidden="true" /></a>
              </div>
            </div>

            <LinkList title="Quick Links" links={QUICK_LINKS} onModal={setModal} />
            <LinkList title="Community" links={COMMUNITY_LINKS} onModal={setModal} />

            <div className="mmf-col">
              <h4 className="foot-h">Contact us</h4>
              <ul className="foot-list">
                <li className="mmf-contact-item">
                  <i className="bi bi-geo-alt-fill foot-icon" aria-hidden="true" />
                  <span>{ADDRESS}</span>
                </li>
                <li className="mmf-contact-item">
                  <i className="bi bi-envelope-fill foot-icon" aria-hidden="true" />
                  <a href="mailto:aaryansmanomilan@gmail.com">aaryansmanomilan@gmail.com</a>
                </li>
                <li className="mmf-contact-item">
                  <i className="bi bi-telephone-fill foot-icon" aria-hidden="true" />
                  <a href="tel:+917030932526">7030932526</a>
                </li>
              </ul>
            </div>
          </div>

          <div className="mmf-bottom">
            <p>&copy; 2025 Aaryans Manomilan. All Rights Reserved.</p>
            <p className="mmf-credit">
              Designed with <i className="bi bi-heart-fill" aria-hidden="true" /> Aayojanai.com
            </p>
          </div>
        </div>
      </footer>

      {modal === "digambar" && <Popup title="Digambar Jain Sub-Castes" onClose={close}><SubCasteList items={DIGAMBAR} /></Popup>}
      {modal === "shwetambar" && <Popup title="Shwetambar Jain Sub-Castes" onClose={close}><SubCasteList items={SHWETAMBAR} /></Popup>}
      {modal === "privacy" && <Popup title="Privacy Policy - Aaryans Manomilan" onClose={close} wide><PrivacyBody /></Popup>}
    </>
  );
}
