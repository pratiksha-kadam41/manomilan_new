import { useEffect, useRef, useState } from "react";

const REGISTER_URL = "#/melava-registration";

const BACKGROUNDS = [
  "/website/bg_slider.jpg",
  "https://images.pexels.com/photos/29450289/pexels-photo-29450289.jpeg",
];

const ig = (path) => `https://www.instagram.com/reel/${path}`;

// img = cover picture in public/videos, href = Instagram reel it opens
const LEFT_REELS = [
  { img: "/videos/stage.jpeg", alt: "Stage Image", href: ig("DY1-7DdTnTk/?igsh=Z3M3bjZ5Y3BxOGxq") },
  { img: "/videos/7000 muli mul.jpeg", alt: "7000 Muli Mul", href: ig("DYUU_XdicAu/?igsh=Yzg2MGZ4dzUxdmRo") },
  { img: "/videos/gandhi.jpeg", alt: "Gandhi", href: ig("DYsC-COzMhW/?igsh=MXFwc3FoaDZjYWRqbA==") },
  { img: "/videos/kondekar.jpeg", alt: "Kondekar", href: ig("DY6iCYrTvlH/?igsh=MXNlb3VzYjc3Y2oxNQ==") },
  { img: "/videos/mamta.jpeg", alt: "Mamta", href: ig("DY6ik19zTCV/?igsh=MXQ0dG9lNWw3OHBkNw==") },
  { img: "/videos/rupresha.jpeg", alt: "Rupresha", href: "#" },
  { img: "/videos/shetty sir.jpeg", alt: "Shetty Sir", href: ig("DYwFWa_CwKW/?igsh=cDRyM3E2bHVoYzNh") },
  { img: "/videos/dagade sir.jpeg", alt: "Dagade Sir", href: ig("DY6jH6OzjiR/?igsh=MTBubWlldG5vOXM0dg") },
];

const RIGHT_REELS = [
  { img: "/videos/new_reel.jpeg", alt: "New Reel", href: ig("DZpd_ZPpd3B/?igsh=M21sdjd1cG9mOG9z") },
  { img: "/videos/new_reel_dz7b.png", alt: "New Reel DZ7b", href: ig("DZ7bHsutz-g/?igsh=cG56emx1c2tuMzZv") },
  { img: "/videos/new_reel_dzzn.png", alt: "New Reel DZZN", href: ig("DZznG7KCtVs/?igsh=MXNscGFjM2V0NTBjdA==") },
  { img: "/videos/new_reel_dzuk.png", alt: "New Reel DZUK", href: ig("DZukKtlJfbZ/?igsh=MWwxM2FjanZ6ZmZ2Nw==") },
  { img: "/videos/new_reel_dak3.png", alt: "New Reel DAK3", href: ig("DaK30XuNa0y/?utm_source=ig_web_copy_link&igsh=MzRlODBiNWFlZA==") },
];

// Phone-shaped frame that scrolls sideways every 3.5 seconds.
function ReelFrame({ reels }) {
  const track = useRef(null);

  useEffect(() => {
    const el = track.current;
    if (!el) return undefined;
    let i = 0;
    const id = setInterval(() => {
      i = (i + 1) % reels.length;
      el.scrollTo({ left: el.clientWidth * i, behavior: "smooth" });
    }, 3500);
    return () => clearInterval(id);
  }, [reels.length]);

  return (
    <div className="reel">
      <div className="reel-track" ref={track}>
        {reels.map((r) => (
          <div className="reel-slide" key={r.img}>
            <a href={r.href} target="_blank" rel="noreferrer">
              <img src={r.img} alt={r.alt} />
              <div className="reel-play">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
              </div>
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}

export default function Hero() {
  const [active, setActive] = useState(0);

  useEffect(() => {
    const id = setInterval(() => setActive((a) => (a + 1) % BACKGROUNDS.length), 5000);
    return () => clearInterval(id);
  }, []);

  return (
    <section id="home" className="hero">
      <div className="hero-bgs">
        {BACKGROUNDS.map((src, i) => (
          <div key={src} className={`hero-bg-image${i === active ? " active" : ""}`}
               style={{ backgroundImage: `url('${src}')` }} />
        ))}
      </div>
      <div className="hero-overlay" />

      <div className="site-container hero-content">
        <div className="d-flex flex-column flex-lg-row align-items-center justify-content-between text-white py-5">

          {/* left: text */}
          <div className="hero-left">
            <h3 className="hero-h3" style={{ fontSize: 34 }}>येथे मनं मिळतात...</h3>
            <h3 className="hero-h3" style={{ fontSize: 44 }}>नाती जुळतात…</h3>

            <div style={{ marginBottom: "2rem" }}>
              <div className="d-flex align-items-center justify-content-center">
                <a href={REGISTER_URL} className="btn-melava">Melava Registration</a>
              </div>
            </div>

            <h2 className="hero-tagline blinking-text">
              Where Souls Connect. <br />Because Every Jain Heart<br />Deserves a True Match.
            </h2>
          </div>

          {/* right: two reel frames (desktop only) */}
          <div className="hero-right">
            <ReelFrame reels={LEFT_REELS} />
            <ReelFrame reels={RIGHT_REELS} />
          </div>
        </div>
      </div>
    </section>
  );
}