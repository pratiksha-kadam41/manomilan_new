import { useEffect, useRef, useState } from "react";
import { request } from "../api";

const REGISTER_URL = "/melava-registration";
const SHOW_WORKSHOP_REGISTRATION = true;

const BACKGROUNDS = [
  "/website/bg_slider.jpg",
  "https://images.pexels.com/photos/29450289/pexels-photo-29450289.jpeg",
];

const ig = (path) => `https://www.instagram.com/reel/${path}`;

// img = cover picture in public/videos, href = Instagram reel it opens
const LEFT_REELS = [
  { img: "/videos/stage.jpeg", alt: "Stage Image", href: ig("DY1-7DdTnTk/") },
  { img: "/videos/7000 muli mul.jpeg", alt: "7000 Muli Mul", href: ig("DYUU_XdicAu/") },
  { img: "/videos/gandhi.jpeg", alt: "Gandhi", href: ig("DYsC-COzMhW/") },
  { img: "/videos/kondekar.jpeg", alt: "Kondekar", href: ig("DY6iCYrTvlH/") },
  { img: "/videos/mamta.jpeg", alt: "Mamta", href: ig("DY6ik19zTCV/") },
  { img: "/videos/shetty sir.jpeg", alt: "Shetty Sir", href: ig("DYwFWa_CwKW/") },
  { img: "/videos/dagade sir.jpeg", alt: "Dagade Sir", href: ig("DY6jH6OzjiR/") },
];

const RIGHT_REELS = [
  { img: "/videos/new_reel.jpeg", alt: "New Reel", href: ig("DZpd_ZPpd3B/") },
  { img: "/videos/new_reel_dz7b.png", alt: "New Reel DZ7b", href: ig("DZ7bHsutz-g/") },
  { img: "/videos/new_reel_dzzn.png", alt: "New Reel DZZN", href: ig("DZznG7KCtVs/") },
  { img: "/videos/new_reel_dzuk.png", alt: "New Reel DZUK", href: ig("DZukKtlJfbZ/") },
  { img: "/videos/new_reel_dak3.png", alt: "New Reel DAK3", href: ig("DaK30XuNa0y/") },
];

// Existing local images used only if a reel thumbnail is unavailable.
const LEFT_FALLBACKS = [
  "/website/gallery1.jpeg",
  "/website/2.jpeg",
  "/website/gallery2.jpeg",
  "/website/3.jpeg",
  "/website/gallery3.jpeg",
  "/website/4.jpeg",
  "/website/gallery4.jpeg",
  "/website/photo101.jpg",
];

const RIGHT_FALLBACKS = [
  "/website/photo501.png",
  "/website/gallery2.jpeg",
  "/website/gallery3.jpeg",
  "/website/gallery4.jpeg",
  "/website/upcoming.jpeg",
];

// Phone-shaped frame that scrolls sideways every 1 second.
function ReelFrame({ reels, title, fallbacks }) {
  const track = useRef(null);

  useEffect(() => {
    const el = track.current;
    if (!el) return undefined;
    let i = 0;
    const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    const id = setInterval(() => {
      i = (i + 1) % reels.length;
      el.scrollTo({ left: el.clientWidth * i, behavior: reduceMotion ? "auto" : "smooth" });
    }, 1000);
    return () => clearInterval(id);
  }, [reels.length]);

  return (
    <div className="mmh-reel-frame">
      {title ? <span className="mmh-reel-caption">{title}</span> : null}
      <div className="reel mmh-reel">
        <div className="reel-track" ref={track}>
          {reels.map((r, i) => (
            <div className="reel-slide" key={r.img}>
              <a href={r.href} target="_blank" rel="noreferrer" aria-label={`${r.alt} — watch on Instagram`}>
                <img
                  src={r.img}
                  alt={r.alt}
                  loading="lazy"
                  onError={(e) => {
                    e.currentTarget.onerror = null;
                    const fallback = fallbacks?.length ? fallbacks[i % fallbacks.length] : "";
                    if (fallback) e.currentTarget.src = fallback;
                  }}
                />
                <span className="reel-play" aria-hidden="true">
                  <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
                </span>
              </a>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export default function Hero() {
  const [active, setActive] = useState(0);
  const [workshopEvents, setWorkshopEvents] = useState([]);
  const [workshopEventsError, setWorkshopEventsError] = useState(false);
  const [workshopEventsLoading, setWorkshopEventsLoading] = useState(true);
  const [melavaEvents, setMelavaEvents] = useState([]);
  const [melavaEventsError, setMelavaEventsError] = useState(false);
  const [melavaEventsLoading, setMelavaEventsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    request("/workshop-events/active", { auth: false })
      .then((response) => {
        if (!cancelled) {
          setWorkshopEvents(Array.isArray(response?.data) ? response.data : []);
          setWorkshopEventsLoading(false);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setWorkshopEventsError(true);
          setWorkshopEventsLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    request("/melava-campaigns/active", { auth: false })
      .then((response) => {
        if (!cancelled) {
          setMelavaEvents(Array.isArray(response?.data) ? response.data : []);
          setMelavaEventsLoading(false);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setMelavaEventsError(true);
          setMelavaEventsLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    const id = setInterval(() => setActive((a) => (a + 1) % BACKGROUNDS.length), 5000);
    return () => clearInterval(id);
  }, []);

  const workshopFee = (() => {
    if (workshopEventsLoading) return "Loading workshop fee…";
    if (workshopEventsError) return "Workshop details unavailable";
    if (workshopEvents.length === 0) return "No active Workshop";
    const currencies = new Set(workshopEvents.map((event) => event.currency || "INR"));
    if (currencies.size > 1) return "Choose a workshop for pricing";
    const fee = Math.min(...workshopEvents.map((event) => Number(event.registration_fee || 0)));
    try {
      const formatted = new Intl.NumberFormat("en-IN", {
        style: "currency",
        currency: workshopEvents[0].currency || "INR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      }).format(fee);
      return workshopEvents.length > 1 ? `From ${formatted}` : `Fee: ${formatted}`;
    } catch {
      return workshopEvents.length > 1 ? `From ${fee}` : `Fee: ${fee}`;
    }
  })();

  const melavaPrice = (() => {
    if (melavaEventsLoading) {
      return { price: "Loading Melava fee…", detail: "" };
    }
    if (melavaEventsError) {
      return { price: "Melava details unavailable", detail: "" };
    }
    if (melavaEvents.length === 0) {
      return { price: "No active Melava", detail: "" };
    }

    const currencies = new Set(melavaEvents.map((campaign) => campaign.currency || "INR"));
    if (currencies.size > 1) {
      return { price: "Select a Melava for pricing", detail: `${melavaEvents.length} active events` };
    }
    const currency = melavaEvents[0].currency || "INR";
    const fee = Math.min(...melavaEvents.map((campaign) => Number(campaign.registration_fee ?? campaign.fee ?? 0)));
    try {
      const formattedFee = new Intl.NumberFormat("en-IN", {
        style: "currency",
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      }).format(fee);
      if (melavaEvents.length > 1) {
        return { price: `From ${formattedFee}`, detail: `${melavaEvents.length} active events` };
      }

      const campaign = melavaEvents[0];
      const bookletFee = Number(campaign.booklet_fee || 0);
      const booklet = campaign.booklet_available && bookletFee > 0
        ? `Booklet: ${new Intl.NumberFormat("en-IN", {
            style: "currency",
            currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
          }).format(bookletFee)}`
        : "Booklet not available";
      return { price: `Fee: ${formattedFee}`, detail: `${campaign.title} · ${booklet}` };
    } catch {
      return {
        price: melavaEvents.length > 1 ? `From ${fee}` : `Fee: ${fee}`,
        detail: melavaEvents.length === 1 ? melavaEvents[0].title : `${melavaEvents.length} active events`,
      };
    }
  })();

  return (
    <section id="home" className="hero mmh-hero">
      <div className="mmh-hero-bgs" aria-hidden="true">
        {BACKGROUNDS.map((src, i) => (
          <div
            key={src}
            className={`mmh-hero-bg-image${i === active ? " active" : ""}`}
            style={{ backgroundImage: `url('${src}')` }}
          />
        ))}
      </div>
      <div className="mmh-hero-pattern" aria-hidden="true" />

      <div className="site-container mmh-hero-content">
        <div className="mmh-hero-grid">
          <div className="mmh-hero-left">
            <span className="mmh-hero-eyebrow">॥ जय जिनेन्द्र ॥</span>

            <h1 className="mmh-hero-title">
              <span className="mmh-hero-title-line">येथे मनं मिळतात...</span>
              <span className="mmh-hero-title-line mmh-hero-title-line--accent">नाती जुळतात…</span>
            </h1>

            <div className="mmh-divider" aria-hidden="true">
              <i className="bi bi-heart-fill" />
            </div>

            <p className="mmh-hero-tagline">
              Where Souls Connect.<br />
              Because Every Jain Heart<br />
              Deserves a True Match.
            </p>

            <div className="mmh-hero-actions">
              <div className="mmh-action-card">
                <a href={REGISTER_URL} className="mmh-btn mmh-btn--primary">
                  <i className="bi bi-calendar-heart" aria-hidden="true" />
                  Melava Registration
                </a>
                <span className="mmh-fee">
                  {melavaPrice.price}
                  {melavaPrice.detail ? <small>{melavaPrice.detail}</small> : null}
                </span>
              </div>

              {SHOW_WORKSHOP_REGISTRATION && (
                <div className="mmh-action-card">
                  <a href="/workshop-registration" className="mmh-btn mmh-btn--secondary">
                    <i className="bi bi-mortarboard" aria-hidden="true" />
                    Workshop Registration
                  </a>
                  <span className="mmh-fee">
                    {workshopFee}
                    {workshopEvents.length > 0 && !workshopEventsError && !workshopEventsLoading ? (
                      <small>
                        {workshopEvents.length === 1
                          ? workshopEvents[0].title
                          : workshopEvents.length > 1
                            ? "Select an active workshop"
                            : ""}
                      </small>
                    ) : null}
                  </span>
                </div>
              )}
            </div>
          </div>

          <div className="mmh-hero-center">
            <span className="mmh-hero-logo-wrap">
              <img
                src="/website/Mano_Milan_Final_Website.png"
                alt="Aaryans Manomilan Logo"
                className="mmh-hero-logo"
              />
            </span>
            <p className="mmh-hero-community">Jain Matrimony Community</p>
            <div className="mmh-hero-lotus" aria-hidden="true">
              <span className="mmh-hero-lotus-line" />
              <i className="bi bi-flower1" />
              <span className="mmh-hero-lotus-line" />
            </div>
          </div>

          <div className="mmh-hero-right">
            <div className="mmh-media-head">
              <span className="mmh-media-kicker">Melava &amp; Community</span>
              <h2 className="mmh-media-title">Moments That Matter</h2>
            </div>

            <div className="mmh-reels">
              <ReelFrame reels={LEFT_REELS} title="Event Highlights" fallbacks={LEFT_FALLBACKS} />
              <ReelFrame reels={RIGHT_REELS} title="Community Reels" fallbacks={RIGHT_FALLBACKS} />
            </div>

            <p className="mmh-media-note">
              <i className="bi bi-instagram" aria-hidden="true" />
              Tap a card to watch the original reel
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
