import { useEffect, useState } from "react";
import { Bell, Calendar, Gift, Megaphone, Sparkles, Star } from "lucide-react";
import { getPublicWebsiteSettings } from "./api.js";
import "./website.css";

const ICONS = {
  calendar: Calendar,
  sparkles: Sparkles,
  megaphone: Megaphone,
  bell: Bell,
  gift: Gift,
  star: Star,
};

export default function WebsiteUpcomingEvents() {
  const [settings, setSettings] = useState(null);

  useEffect(() => {
    let active = true;
    getPublicWebsiteSettings().then((data) => {
      if (active) setSettings(data?.upcoming_events || null);
    });
    return () => {
      active = false;
    };
  }, []);

  if (!settings?.enabled) return null;

  const Icon = ICONS[settings.icon] || Calendar;
  const position = settings.position === "bottom-left" ? "bottom-left" : "bottom-right";
  const style = {
    "--website-event-bg": settings.background_color || "#c4144f",
    "--website-event-color": settings.text_color || "#ffffff",
  };
  const content = (
    <>
      <Icon size={18} strokeWidth={2.4} />
      <span>{settings.label || "Upcoming Events"}</span>
    </>
  );

  if (settings.url) {
    return (
      <a
        className={`website-event-btn ${position}`}
        style={style}
        href={settings.url}
        target={settings.open_new_tab ? "_blank" : undefined}
        rel={settings.open_new_tab ? "noopener noreferrer" : undefined}
      >
        {content}
      </a>
    );
  }

  return (
    <button type="button" className={`website-event-btn ${position} static`} style={style}>
      {content}
    </button>
  );
}
