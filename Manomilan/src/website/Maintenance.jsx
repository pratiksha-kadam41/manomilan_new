import { useEffect, useState } from "react";
import { RefreshCw, Settings2, TimerReset } from "lucide-react";
import { formatRemaining, getPublicWebsiteSettings } from "./api.js";
import "./website.css";

export default function Maintenance({ settings: initialSettings = null }) {
  const [settings, setSettings] = useState(initialSettings);
  const [remaining, setRemaining] = useState(Number(initialSettings?.seconds_remaining || 0));

  useEffect(() => {
    if (initialSettings) return;
    getPublicWebsiteSettings({ force: true }).then((data) => {
      setSettings(data?.maintenance || null);
      setRemaining(Number(data?.maintenance?.seconds_remaining || 0));
    });
  }, [initialSettings]);

  useEffect(() => {
    if (!remaining) return undefined;
    const timer = window.setInterval(() => {
      setRemaining((value) => Math.max(0, value - 1));
    }, 1000);
    return () => window.clearInterval(timer);
  }, [remaining]);

  return (
    <main className="maintenance-page">
      <section className="maintenance-card">
        <div className="maintenance-icon">
          <Settings2 size={34} />
        </div>
        <span className="maintenance-eyebrow">Scheduled maintenance</span>
        <h1>{settings?.title || "We will be back soon"}</h1>
        <p>{settings?.message || "The website is being updated. Please check back shortly."}</p>
        {settings?.ends_at ? (
          <div className="maintenance-countdown">
            <TimerReset size={18} />
            <span>Estimated return in {formatRemaining(remaining)}</span>
          </div>
        ) : null}
        <div className="maintenance-actions">
          <button type="button" onClick={() => window.location.reload()}>
            <RefreshCw size={16} />
            Try again
          </button>
        </div>
      </section>
    </main>
  );
}
