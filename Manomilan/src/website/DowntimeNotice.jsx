import { useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Mail, Phone, TimerReset, X } from "lucide-react";
import { formatRemaining, getPublicWebsiteSettings } from "./api.js";
import "./website.css";

function LotusMark() {
  return (
    <svg viewBox="0 0 72 46" role="presentation" focusable="false">
      <defs>
        <linearGradient id="wmLotusGold" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#e7c48c" />
          <stop offset="1" stopColor="#b98243" />
        </linearGradient>
      </defs>
      <g fill="url(#wmLotusGold)">
        <path d="M36 2c5.4 7.3 8.1 13.4 8.1 18.2 0 4.5-3.6 8.1-8.1 8.1s-8.1-3.6-8.1-8.1C27.9 15.4 30.6 9.3 36 2Z" />
        <path d="M35.7 12.2C28.8 9.8 21.3 12.7 17 20c5.4 5.1 11.6 7.6 18.7 7.6V12.2Z" opacity=".86" />
        <path d="M36.3 12.2c6.9-2.4 14.4.5 18.7 7.8-5.4 5.1-11.6 7.6-18.7 7.6V12.2Z" opacity=".86" />
        <path d="M35.4 18.2C27 17 18.6 21.6 15 30.3c7.1 6.6 14.2 9.7 21.4 9.7V18.2Z" opacity=".72" />
        <path d="M36.6 18.2c8.4-1.2 16.8 3.4 20.4 12.1-7.1 6.6-14.2 9.7-21.4 9.7V18.2Z" opacity=".72" />
        <path d="M11 27.5c5.5 9.1 14 13.7 25 13.7s19.5-4.6 25-13.7c-6.2 4.1-14.5 6.3-25 6.3S17.2 31.6 11 27.5Z" opacity=".55" />
      </g>
      <circle cx="36" cy="22" r="2.6" fill="#7a1638" />
    </svg>
  );
}

function MaintenanceIllustration() {
  return (
    <svg viewBox="0 0 340 180" role="presentation" focusable="false">
      <defs>
        <radialGradient id="wmArtGlow" cx="50%" cy="50%" r="50%">
          <stop offset="0" stopColor="#ffffff" stopOpacity=".95" />
          <stop offset="1" stopColor="#f6bfd2" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="wmArtPetal" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#fbe3eb" />
          <stop offset="1" stopColor="#d65c86" />
        </linearGradient>
        <linearGradient id="wmArtPetalDeep" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#c93d6c" />
          <stop offset="1" stopColor="#7a1638" />
        </linearGradient>
        <linearGradient id="wmArtGold" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#f0d19a" />
          <stop offset="1" stopColor="#b98243" />
        </linearGradient>
      </defs>

      <ellipse cx="170" cy="132" rx="116" ry="34" fill="url(#wmArtGlow)" />
      <ellipse cx="170" cy="139" rx="72" ry="11" fill="#b02a54" opacity=".12" />

      <g transform="translate(170 92)">
        <g fill="url(#wmArtPetal)" opacity=".9">
          <ellipse cx="0" cy="-44" rx="20" ry="44" />
          <ellipse cx="0" cy="-44" rx="20" ry="44" transform="rotate(45)" />
          <ellipse cx="0" cy="-44" rx="20" ry="44" transform="rotate(90)" />
          <ellipse cx="0" cy="-44" rx="20" ry="44" transform="rotate(135)" />
        </g>
        <g fill="url(#wmArtPetalDeep)" opacity=".9">
          <ellipse cx="0" cy="-30" rx="15" ry="32" transform="rotate(22.5)" />
          <ellipse cx="0" cy="-30" rx="15" ry="32" transform="rotate(67.5)" />
          <ellipse cx="0" cy="-30" rx="15" ry="32" transform="rotate(112.5)" />
          <ellipse cx="0" cy="-30" rx="15" ry="32" transform="rotate(157.5)" />
        </g>
        <circle r="30" fill="#fff8fb" stroke="url(#wmArtGold)" strokeWidth="3" />
        <circle r="18" fill="#7a1638" />
        <g stroke="#f7d8a8" strokeWidth="3" strokeLinecap="round">
          <path d="M0-25v-7" />
          <path d="M0 25v7" />
          <path d="M-25 0h-7" />
          <path d="M25 0h7" />
          <path d="M-17.7-17.7l-5-5" />
          <path d="M17.7-17.7l5-5" />
          <path d="M-17.7 17.7l-5 5" />
          <path d="M17.7 17.7l5 5" />
        </g>
        <circle r="7" fill="#fff8fb" />
      </g>

      <g fill="url(#wmArtGold)">
        <path d="M58 54l4.2 10.8L73 69l-10.8 4.2L58 84l-4.2-10.8L43 69l10.8-4.2L58 54Z" opacity=".8" />
        <path d="M284 44l3 7.8 7.8 3-7.8 3-3 7.8-3-7.8-7.8-3 7.8-3 3-7.8Z" opacity=".7" />
        <circle cx="296" cy="96" r="3.5" opacity=".65" />
        <circle cx="46" cy="112" r="2.8" opacity=".6" />
      </g>
    </svg>
  );
}

export default function DowntimeNotice({ placement = "home" }) {
  const [downtime, setDowntime] = useState(null);
  const [remaining, setRemaining] = useState(0);
  const [dismissed, setDismissed] = useState(false);
  const closeRef = useRef(null);
  const titleId = useId();
  const descriptionId = useId();

  useEffect(() => {
    let active = true;
    getPublicWebsiteSettings().then((data) => {
      if (!active) return;
      setDowntime(data?.downtime || null);
      setRemaining(Number(data?.downtime?.seconds_remaining || 0));
    });
    return () => {
      active = false;
    };
  }, []);

  const hasRemaining = remaining > 0;

  useEffect(() => {
    if (!downtime?.active || !downtime?.show_countdown || !hasRemaining) return undefined;
    const timer = window.setInterval(() => {
      setRemaining((value) => Math.max(0, value - 1));
    }, 1000);
    return () => window.clearInterval(timer);
  }, [downtime?.active, downtime?.show_countdown, hasRemaining]);

  const visible = useMemo(() => {
    if (!downtime?.active) return false;
    return downtime.display_location === "both" || downtime.display_location === placement;
  }, [downtime, placement]);

  const open = visible && !dismissed;

  useEffect(() => {
    if (!open) return undefined;

    const previousOverflow = document.body.style.overflow;
    const handleKeyDown = (event) => {
      if (event.key === "Escape") setDismissed(true);
    };

    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", handleKeyDown);
    const focusTimer = window.setTimeout(() => closeRef.current?.focus(), 60);

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", handleKeyDown);
      window.clearTimeout(focusTimer);
    };
  }, [open]);

  if (!open || typeof document === "undefined") return null;

  const title = downtime.title || "Scheduled Downtime";
  const showCountdown = downtime.show_countdown && downtime.ends_at;
  const showContact = downtime.show_contact && (downtime.contact_email || downtime.contact_phone);
  const hasDetails = showCountdown || showContact;

  return createPortal(
    <div
      className="website-downtime-overlay"
      role="presentation"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) setDismissed(true);
      }}
    >
      <section
        className="website-downtime-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={downtime.message ? descriptionId : undefined}
      >
        <button
          ref={closeRef}
          type="button"
          className="website-downtime-close"
          onClick={() => setDismissed(true)}
          aria-label="Close announcement"
        >
          <X size={18} />
        </button>

        <div className="website-downtime-lotus" aria-hidden="true">
          <LotusMark />
        </div>

        <p className="website-downtime-blessing">॥ जय जिनेन्द्र ॥</p>
        <h2 className="website-downtime-title" id={titleId}>{title}</h2>

        {downtime.message ? (
          <p className="website-downtime-message" id={descriptionId}>{downtime.message}</p>
        ) : null}

        <div className="website-downtime-art" aria-hidden="true">
          <MaintenanceIllustration />
        </div>

        {hasDetails ? (
          <div className="website-downtime-details">
            {showCountdown ? (
              <span className="website-downtime-detail countdown">
                <TimerReset size={15} />
                Back in {formatRemaining(remaining)}
              </span>
            ) : null}
            {showContact && downtime.contact_email ? (
              <a className="website-downtime-detail" href={`mailto:${downtime.contact_email}`}>
                <Mail size={15} />
                {downtime.contact_email}
              </a>
            ) : null}
            {showContact && downtime.contact_phone ? (
              <a className="website-downtime-detail" href={`tel:${downtime.contact_phone}`}>
                <Phone size={15} />
                {downtime.contact_phone}
              </a>
            ) : null}
          </div>
        ) : null}

        <button
          type="button"
          className="website-downtime-action"
          onClick={() => setDismissed(true)}
        >
          Got it
        </button>
      </section>
    </div>,
    document.body,
  );
}
