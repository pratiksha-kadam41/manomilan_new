import { useEffect, useState } from "react";

export default function StatCard({ value, label, color, percent, icon: Icon }) {
  const [displayValue, setDisplayValue] = useState(value === "—" ? value : 0);

  useEffect(() => {
    if (value === "—" || typeof value !== "number") {
      setDisplayValue(value);
      return;
    }

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      setDisplayValue(value);
      return;
    }

    let frame;
    const start = performance.now();
    const duration = 650;
    const animate = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      setDisplayValue(Math.round(value * eased));
      if (progress < 1) frame = requestAnimationFrame(animate);
    };
    frame = requestAnimationFrame(animate);
    return () => cancelAnimationFrame(frame);
  }, [value]);

  return (
    <div className="adm-card">
      <div className="adm-card-top">
        <div className="adm-icon" style={{ background: color }}>
          {Icon ? <Icon size={26} strokeWidth={2} /> : null}
        </div>
        <div>
          <div className="adm-number">{displayValue}</div>
          <div className="adm-label">{label}</div>
        </div>
      </div>
      {percent !== undefined && (
        <div className="adm-progress-wrap">
          <div className="adm-progress">
            <div style={{ width: `${percent}%`, background: color }} />
          </div>
          <small>{percent}% {label.split(" ")[0]}</small>
        </div>
      )}
    </div>
  );
}