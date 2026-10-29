import { useEffect, useRef, useState } from "react";

// Top 4 stat cards. target = number the counter animates up to.
const STATS = [
  { target: 11351, label: "Total Profiles", icon: "fa-users" },
  { target: 8420, label: "Successful Matches", icon: "fa-ring" },
  { target: 10300, label: "Verified Profiles", icon: "fa-globe" },
  { target: 5, label: "Years of Match-Making", icon: "fa-heart" },
];

// Bottom 3 feature cards.
const FEATURES = [
  {
    icon: "fa-shield-alt",
    title: "100% Verified Profiles",
    text: "All profiles undergo strict verification to ensure safety and genuineness, giving you a secure platform for searching.",
  },
  {
    icon: "fa-search-dollar",
    title: "Advanced Filter Search",
    text: "Filter profiles based on sect, sub-sect, location, education, and profession to find highly compatible matches instantly.",
  },
  {
    icon: "fa-handshake",
    title: "Dedicated Support",
    text: "Receive personalized assistance from our relationship managers to guide you through the matching and introduction process.",
  },
];

// Number that counts from 0 to `target` when it scrolls into view (once).
function Counter({ target }) {
  const ref = useRef(null);
  const [value, setValue] = useState(0);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    let frame;

    const run = () => {
      const duration = 1200;
      const t0 = performance.now();
      const step = (now) => {
        const p = Math.min((now - t0) / duration, 1);
        setValue(Math.floor(p * target));
        if (p < 1) frame = requestAnimationFrame(step);
      };
      frame = requestAnimationFrame(step);
    };

    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          run();
          io.disconnect();
        }
      },
      { threshold: 0.4 }
    );
    io.observe(el);

    return () => {
      io.disconnect();
      cancelAnimationFrame(frame);
    };
  }, [target]);

  return <span ref={ref}>{value.toLocaleString("en-IN")}</span>;
}

export default function OurServices() {
  return (
    <main>
      <section className="os-section">
        <div className="site-container">
          <div className="os-head">
            <h2>Our Services and Features</h2>
            <p>Designed to make your search seamless and successful.</p>
          </div>

          <div className="os-stats">
            {STATS.map((s) => (
              <div key={s.label} className="os-stat">
                <div className="os-stat-num">
                  <Counter target={s.target} />
                </div>
                <div className="os-stat-label">{s.label}</div>
                <div className="os-stat-icon">
                  <i className={`fas ${s.icon}`} />
                </div>
              </div>
            ))}
          </div>

          <div className="os-features">
            {FEATURES.map((f) => (
              <div key={f.title} className="os-feature">
                <div className="os-feature-icon">
                  <i className={`fas ${f.icon}`} />
                </div>
                <h3>{f.title}</h3>
                <p>{f.text}</p>
              </div>
            ))}
          </div>
        </div>
      </section>
    </main>
  );
}
