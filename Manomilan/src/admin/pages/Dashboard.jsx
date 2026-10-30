import { memo, useCallback, useEffect, useMemo, useRef, useState } from "react";
import AdminLayout from "../components/AdminLayout";
import { getAdminDashboard } from "../../auth";
import { Link } from "react-router-dom";
import {
  ArrowUpRight, BadgeCheck, CalendarDays, CalendarPlus, ChevronRight, CircleDollarSign,
  Clock3, Download, Heart, HeartHandshake, ListChecks, RefreshCw, ShieldCheck, Sparkles,
  UserCheck, UserPlus, UserRound, UserRoundCheck, UsersRound, Wallet,
} from "lucide-react";
import "../admin.css";

/* =====================================================================
   1. CONFIG — edit routes here to match your admin panel
   ===================================================================== */

const ROUTES = {
  addProfile: "/admin/profiles",
  profiles: "/admin/profiles",
  verify: "/admin/profiles",
  events: "/admin/melava-campaigns",
  createEvent: "/admin/melava-campaigns",
  registrations: "/admin/melava",
  payments: "/admin/melava",
};

const AppLink = ({ to, children, ...rest }) => <Link to={to} {...rest}>{children}</Link>;

const PERIODS = [
  { id: "today", label: "Today" },
  { id: "week", label: "This week" },
  { id: "custom", label: "Between dates" },
  { id: "all", label: "All time" },
];

const QUICK_ACTIONS = [
  { label: "Add profile", hint: "Create a new candidate", icon: UserPlus, to: ROUTES.addProfile, tone: "teal" },
  { label: "Verify profiles", hint: "Review pending profiles", icon: ShieldCheck, to: ROUTES.verify, tone: "green" },
  { label: "New Melava event", hint: "Schedule a meet-up", icon: CalendarPlus, to: ROUTES.createEvent, tone: "violet" },
  { label: "Registrations", hint: "See who signed up", icon: ListChecks, to: ROUTES.registrations, tone: "blue" },
  { label: "Payments", hint: "Verify event payments", icon: Wallet, to: ROUTES.payments, tone: "amber" },
];

const KPI_CARDS = [
  { id: "users", label: "Total users", color: "#7c6df0", icon: UsersRound, to: ROUTES.profiles },
  { id: "total", label: "Total profiles", color: "#2f9bf0", icon: UserRound, to: ROUTES.profiles },
  { id: "verified", label: "Verified profiles", color: "#1ec99b", icon: BadgeCheck, to: ROUTES.profiles },
  { id: "unverified", label: "Unverified profiles", color: "#e5484d", icon: Clock3, to: ROUTES.verify },
  { id: "groom", label: "Groom candidates", color: "#4f46e5", icon: UserCheck, to: ROUTES.profiles },
  { id: "bride", label: "Bride candidates", color: "#ec4899", icon: UserRoundCheck, to: ROUTES.profiles },
  { id: "registrations", label: "Melava registrations", color: "#0ea5e9", icon: HeartHandshake, to: ROUTES.registrations },
  { id: "pendingPay", label: "Pending payments", color: "#f59e0b", icon: CircleDollarSign, to: ROUTES.payments },
  { id: "verifiedPay", label: "Verified payments", color: "#14b8a6", icon: BadgeCheck, to: ROUTES.payments },
  { id: "events", label: "Melava events", color: "#8b5cf6", icon: CalendarDays, to: ROUTES.events },
];

const TYPE_FILTERS = [
  { id: "all", label: "All" },
  { id: "groom", label: "Groom" },
  { id: "bride", label: "Bride" },
];

const GRADIENTS = [
  ["#1f7a6f", "#164e63"], ["#ec4899", "#8b5cf6"], ["#f59e0b", "#ef4444"],
  ["#0ea5e9", "#6366f1"], ["#14b8a6", "#65a30d"], ["#f43f5e", "#f97316"],
];

const RECENT_LIMIT = 5;
const AUTO_REFRESH_MS = 60000;

/* =====================================================================
   2. HELPERS (created once)
   ===================================================================== */

const DATE_FMT = new Intl.DateTimeFormat("en-IN", { day: "numeric", month: "short", year: "numeric" });
const MONTH_FMT = new Intl.DateTimeFormat("en-IN", { month: "short" });
const TIME_FMT = new Intl.DateTimeFormat("en-IN", { hour: "numeric", minute: "2-digit" });
const TODAY_FMT = new Intl.DateTimeFormat("en-US", { weekday: "long", year: "numeric", month: "long", day: "numeric" });
const RTF = new Intl.RelativeTimeFormat("en", { numeric: "auto" });
const REDUCED_MOTION =
  typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

const slug = (v) => String(v || "").toLowerCase().replace(/\s+/g, "-");
const parseDay = (v) => (v ? new Date(`${v}T00:00:00`) : null);
const hash = (s) => [...String(s || "x")].reduce((h, c) => (h * 31 + c.charCodeAt(0)) | 0, 7);
const gradientFor = (s) => GRADIENTS[Math.abs(hash(s)) % GRADIENTS.length];
const initials = (name) =>
  String(name || "?").trim().split(/\s+/).slice(0, 2).map((w) => w[0]?.toUpperCase()).join("") || "?";

const getGreeting = () => {
  const h = new Date().getHours();
  return h < 12 ? "Good morning" : h < 17 ? "Good afternoon" : "Good evening";
};

const timeAgo = (value) => {
  const d = new Date(value);
  if (!value || Number.isNaN(d.getTime())) return "";
  const diff = (d - Date.now()) / 1000;
  const abs = Math.abs(diff);
  if (abs < 60) return "just now";
  if (abs < 3600) return RTF.format(Math.round(diff / 60), "minute");
  if (abs < 86400) return RTF.format(Math.round(diff / 3600), "hour");
  if (abs < 2592000) return RTF.format(Math.round(diff / 86400), "day");
  return DATE_FMT.format(d);
};

const formatEventDate = (e) => {
  const start = parseDay(e?.start_date);
  if (!start) return "Dates not set";
  const end = parseDay(e.end_date);
  if (!end || e.end_date === e.start_date) return DATE_FMT.format(start);
  return `${DATE_FMT.format(start)} – ${DATE_FMT.format(end)}`;
};

const countdown = (e) => {
  const start = parseDay(e?.start_date);
  if (!start) return "Date TBD";
  const now = new Date();
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const days = Math.round((start - todayStart) / 86400000);
  if (days > 1) return `In ${days} days`;
  if (days === 1) return "Tomorrow";
  if (days === 0) return "Today";
  const end = parseDay(e.end_date);
  return end && todayStart <= end ? "Happening now" : "Ended";
};

const downloadCsv = (rows, filename) => {
  const csv = rows
    .map((r) => r.map((c) => `"${String(c ?? "").replace(/"/g, '""')}"`).join(","))
    .join("\r\n");
  const url = URL.createObjectURL(new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" }));
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 0);
};

/* Smooth count-up for numbers; skips animation for reduced-motion users. */
function useCountUp(target, duration = 800) {
  const [value, setValue] = useState(typeof target === "number" ? target : 0);
  const prev = useRef(0);

  useEffect(() => {
    if (typeof target !== "number") return undefined;
    if (REDUCED_MOTION) {
      setValue(target);
      prev.current = target;
      return undefined;
    }
    const from = prev.current;
    const start = performance.now();
    let raf;
    const tick = (now) => {
      const t = Math.min((now - start) / duration, 1);
      setValue(Math.round(from + (target - from) * (1 - Math.pow(1 - t, 3))));
      if (t < 1) raf = requestAnimationFrame(tick);
      else prev.current = target;
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [target, duration]);

  return typeof target === "number" ? value : null;
}

/* =====================================================================
   3. SMALL COMPONENTS
   ===================================================================== */

/* Shows a photo when available; falls back to children if missing or broken. */
function SafeImage({ src, alt = "", className, children }) {
  const [failed, setFailed] = useState(false);
  if (!src || failed) return children;
  return (
    <img className={className} src={src} alt={alt} loading="lazy" decoding="async" onError={() => setFailed(true)} />
  );
}

const Avatar = memo(function Avatar({ profile }) {
  const [a, b] = gradientFor(profile.name);
  const photo = profile.photo || profile.photo_url || profile.image || profile.avatar;
  return (
    <span className={`dx-avatar ${slug(profile.type)}`} style={{ "--g1": a, "--g2": b }}>
      <SafeImage src={photo} alt="" className="dx-avatar-img">
        <span aria-hidden="true">{initials(profile.name)}</span>
      </SafeImage>
    </span>
  );
});

const HeroArt = memo(function HeroArt() {
  return (
    <div className="dx-hero-art" aria-hidden="true">
      <img src="/img/admindash.png" alt="" />
      <span className="dx-hero-heart dx-hero-heart-one">♥</span>
      <span className="dx-hero-heart dx-hero-heart-two">♥</span>
      <span className="dx-hero-heart dx-hero-heart-three">♥</span>
      <span className="dx-hero-flower dx-hero-flower-one">✿</span>
      <span className="dx-hero-flower dx-hero-flower-two">✿</span>
    </div>
  );
});

const KpiCard = memo(function KpiCard({ item, value, index }) {
  const Icon = item.icon;
  const shown = useCountUp(value);
  return (
    <AppLink to={item.to} className="dx-kpi" style={{ "--c": item.color, "--i": index }}>
      <span className="dx-kpi-icon"><Icon size={22} aria-hidden="true" /></span>
      <span className="dx-kpi-body">
        <strong>{shown === null ? "—" : shown.toLocaleString("en-IN")}</strong>
        <small>{item.label}</small>
      </span>
      <ArrowUpRight className="dx-kpi-go" size={16} aria-hidden="true" />
    </AppLink>
  );
});

const Donut = memo(function Donut({ pct, colorA, colorB, empty, children }) {
  return (
    <div className={`dx-donut${empty ? " is-empty" : ""}`} style={{ "--p": pct, "--a": colorA, "--b": colorB }}>
      <div className="dx-donut-hole">{children}</div>
    </div>
  );
});

const RecentRow = memo(function RecentRow({ profile, index }) {
  return (
    <li className="dx-person" style={{ "--i": index }}>
      <Avatar profile={profile} />
      <div className="dx-person-main">
        <strong title={profile.name}>{profile.name || "Unnamed"}</strong>
        <small>
          {profile.location || "Location not set"}
          {profile.created_at ? ` · ${timeAgo(profile.created_at)}` : ""}
        </small>
      </div>
      <span className={`dx-pill dx-type ${slug(profile.type)}`}>{profile.type}</span>
      <span className={`dx-pill dx-status ${slug(profile.status)}`}>{profile.status}</span>
      {profile.id != null ? (
        <AppLink to={`${ROUTES.profiles}/${profile.id}`} className="dx-person-go" aria-label={`Open ${profile.name || "profile"}`}>
          <ChevronRight size={18} aria-hidden="true" />
        </AppLink>
      ) : null}
    </li>
  );
});

const EventCard = memo(function EventCard({ event, index }) {
  const [a, b] = gradientFor(event.title);
  const date = parseDay(event.start_date);
  const cover = event.image || event.banner || event.cover_image || event.thumbnail;
  const count = Number(event.registrations_count || 0);
  const capacity = Number(event.capacity || event.max_registrations || 0);
  const pct = capacity ? Math.min(Math.round((count / capacity) * 100), 100) : 0;

  return (
    <article className="dx-event" style={{ "--g1": a, "--g2": b, "--i": index }}>
      <div className="dx-event-cover">
        <SafeImage src={cover} alt="" className="dx-event-img">
          <Heart className="dx-event-heart" size={34} aria-hidden="true" />
        </SafeImage>
        <span className="dx-event-date">
          <strong>{date ? date.getDate() : "—"}</strong>
          <small>{date ? MONTH_FMT.format(date) : "Date"}</small>
        </span>
        <span className="dx-event-when">{countdown(event)}</span>
      </div>
      <div className="dx-event-body">
        <strong title={event.title}>{event.title}</strong>
        <span>{formatEventDate(event)}</span>
        {capacity ? <div className="dx-meter" aria-hidden="true"><i style={{ width: `${pct}%` }} /></div> : null}
        <small>
          {count} registered{capacity ? ` of ${capacity}` : ""}
          {event.schedule_status ? <em className={`dx-event-status ${slug(event.schedule_status)}`}>{event.schedule_status}</em> : null}
        </small>
      </div>
    </article>
  );
});

/* =====================================================================
   4. PAGE
   ===================================================================== */

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const [error, setError] = useState("");
  const [period, setPeriod] = useState("all");
  const [fromDate, setFromDate] = useState("");
  const [toDate, setToDate] = useState("");
  const [loading, setLoading] = useState(true);
  const [refreshKey, setRefreshKey] = useState(0);
  const [auto, setAuto] = useState(false);
  const [updatedAt, setUpdatedAt] = useState(null);
  const [recentType, setRecentType] = useState("all");
  const silentRef = useRef(false);

  const customReady = period !== "custom" || Boolean(fromDate && toDate);

  /* ---- data ---- */
  useEffect(() => {
    if (!customReady) return undefined;
    let active = true;
    if (!silentRef.current) setLoading(true);
    silentRef.current = false;
    setError("");

    const params = period === "custom"
      ? { force: true, period: "", from: fromDate, to: toDate }
      : { force: true, period };

    getAdminDashboard(params)
      .then((data) => { if (active) { setStats(data); setUpdatedAt(new Date()); } })
      .catch((err) => { if (active) setError(err?.message || "Could not load dashboard data."); })
      .finally(() => { if (active) setLoading(false); });

    return () => { active = false; };
  }, [period, fromDate, toDate, customReady, refreshKey]);

  /* Auto refresh: quiet background reload, paused while the tab is hidden. */
  useEffect(() => {
    if (!auto) return undefined;
    const id = setInterval(() => {
      if (document.hidden) return;
      silentRef.current = true;
      setRefreshKey((k) => k + 1);
    }, AUTO_REFRESH_MS);
    return () => clearInterval(id);
  }, [auto]);

  const handleRefresh = useCallback(() => setRefreshKey((k) => k + 1), []);

  /* ---- derived numbers ---- */
  const m = useMemo(() => {
    const n = (key) => Number(stats?.[key] || 0);
    const total = n("total_profiles");
    const verified = n("verified_profiles");
    const groom = n("groom_profiles");
    const bride = n("bride_profiles");
    const pendingPay = n("pending_melava_payments");
    const verifiedPay = n("verified_melava_payments");
    const payTotal = pendingPay + verifiedPay;
    const candidates = groom + bride;
    return {
      users: n("total_users"), total, verified,
      unverified: Math.max(total - verified, 0),
      groom, bride, candidates,
      registrations: n("melava_registrations"),
      pendingPay, verifiedPay, payTotal,
      events: n("melava_campaigns"),
      verifiedPct: total ? Math.round((verified / total) * 100) : 0,
      groomPct: candidates ? Math.round((groom / candidates) * 100) : 0,
      paidPct: payTotal ? Math.round((verifiedPay / payTotal) * 100) : 0,
    };
  }, [stats]);

  const attention = useMemo(() => {
    if (!stats) return [];
    const list = [];
    if (m.unverified) list.push({ text: `${m.unverified} profile${m.unverified > 1 ? "s" : ""} to verify`, to: ROUTES.verify });
    if (m.pendingPay) list.push({ text: `${m.pendingPay} payment${m.pendingPay > 1 ? "s" : ""} pending`, to: ROUTES.payments });
    if (!(stats.upcoming_melava_events || []).length) list.push({ text: "No upcoming event — create one", to: ROUTES.createEvent });
    return list;
  }, [stats, m]);

  const recent = useMemo(() => {
    const list = [...(stats?.recent_profiles || [])].sort(
      (a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0),
    );
    const filtered = recentType === "all" ? list : list.filter((p) => slug(p.type) === recentType);
    return filtered.slice(0, RECENT_LIMIT);
  }, [stats, recentType]);

  const events = useMemo(() => (stats?.upcoming_melava_events || []).slice(0, 3), [stats]);
  const header = useMemo(() => ({ greeting: getGreeting(), today: TODAY_FMT.format(new Date()) }), []);

  const handleExport = useCallback(() => {
    if (!stats) return;
    const rows = [["Metric", "Value"], ...KPI_CARDS.map((c) => [c.label, m[c.id]])];
    rows.push([], ["Recent registrations"], ["Name", "Type", "Location", "Registered", "Status"]);
    recent.forEach((p) => rows.push([p.name, p.type, p.location, p.created_at, p.status]));
    downloadCsv(rows, `manomilan-dashboard-${period}-${new Date().toISOString().slice(0, 10)}.csv`);
  }, [stats, m, recent, period]);

  const busy = loading && customReady;

  return (
    <AdminLayout currentPath="/admin">
      <div className="dx-page">
        {/* ---------- Hero ---------- */}
        <header className="dx-hero">
          <div className="dx-hero-copy">
            <span className="dx-kicker"><Sparkles size={14} aria-hidden="true" /> Welcome to Manomilan</span>
            <h1>{header.greeting}, Super Admin</h1>
            <p className="dx-hero-summary">Here’s what’s happening with Manomilan today.</p>
            <p>{header.today}</p>
            <div className="dx-attention" aria-live="polite">
              {stats && !attention.length ? <span className="dx-clear">You’re all caught up</span> : null}
              {attention.map((item) => (
                <AppLink key={item.text} to={item.to} className="dx-attn-chip">
                  <i aria-hidden="true" />{item.text}<ChevronRight size={14} aria-hidden="true" />
                </AppLink>
              ))}
            </div>
          </div>
          <HeroArt />
        </header>

        {/* ---------- Quick actions ---------- */}
        <nav className="dx-actions" aria-label="Quick actions">
          {QUICK_ACTIONS.map(({ label, hint, icon: Icon, to, tone }, i) => (
            <AppLink key={label} to={to} className={`dx-action ${tone}`} style={{ "--i": i }}>
              <span className="dx-action-icon"><Icon size={20} aria-hidden="true" /></span>
              <span><strong>{label}</strong><small>{hint}</small></span>
            </AppLink>
          ))}
        </nav>

        {/* ---------- Toolbar ---------- */}
        <section className="dx-toolbar" aria-label="Dashboard period">
          <div className="dx-tabs" role="group" aria-label="Date filter">
            {PERIODS.map((p) => (
              <button key={p.id} type="button" className={period === p.id ? "active" : ""}
                aria-pressed={period === p.id} onClick={() => setPeriod(p.id)}>
                {p.label}
              </button>
            ))}
          </div>

          {period === "custom" ? (
            <div className="dx-dates">
              <label>From<input type="date" value={fromDate} max={toDate || undefined} onChange={(e) => setFromDate(e.target.value)} /></label>
              <label>To<input type="date" value={toDate} min={fromDate || undefined} onChange={(e) => setToDate(e.target.value)} /></label>
            </div>
          ) : null}

          <div className="dx-tools">
            {updatedAt ? <small className="dx-updated">Updated {TIME_FMT.format(updatedAt)}</small> : null}
            <label className="dx-switch">
              <input type="checkbox" checked={auto} onChange={(e) => setAuto(e.target.checked)} />
              <span aria-hidden="true" />
              Auto refresh
            </label>
            <button type="button" className="dx-btn" onClick={handleExport} disabled={!stats}>
              <Download size={16} aria-hidden="true" /> Export
            </button>
            <button type="button" className="dx-btn primary" onClick={handleRefresh} disabled={busy}>
              <RefreshCw size={16} className={busy ? "dx-spin" : ""} aria-hidden="true" /> {busy ? "Updating…" : "Refresh"}
            </button>
          </div>
        </section>

        {error ? (
          <div className="dx-alert" role="alert">
            <span>{error}</span>
            <button type="button" onClick={handleRefresh}>Try again</button>
          </div>
        ) : null}
        {period === "custom" && !customReady ? (
          <p className="dx-hint">Choose both a start and an end date to see results.</p>
        ) : null}

        {/* ---------- KPI cards ---------- */}
        <section className={`dx-kpis${busy ? " is-busy" : ""}`} aria-busy={busy} aria-label="Key numbers">
          {KPI_CARDS.map((item, i) => (
            <KpiCard key={item.id} item={item} index={i} value={stats ? m[item.id] : null} />
          ))}
        </section>

        {/* ---------- Insights ---------- */}
        <section className="dx-insights" aria-label="Insights">
          <article className="dx-panel" style={{ "--i": 0 }}>
            <h2>Groom &amp; bride balance</h2>
            <div className="dx-insight-row">
              <Donut pct={m.groomPct} colorA="#4f46e5" colorB="#ec4899" empty={!m.candidates}>
                <strong>{m.candidates.toLocaleString("en-IN")}</strong><span>candidates</span>
              </Donut>
              <ul className="dx-legend">
                <li><i style={{ background: "#4f46e5" }} />Grooms<b>{m.groom.toLocaleString("en-IN")}</b></li>
                <li><i style={{ background: "#ec4899" }} />Brides<b>{m.bride.toLocaleString("en-IN")}</b></li>
              </ul>
            </div>
          </article>

          <article className="dx-panel" style={{ "--i": 1 }}>
            <h2>Profile verification</h2>
            <div className="dx-insight-row">
              <Donut pct={m.verifiedPct} colorA="#1ec99b" colorB="#e9eef2" empty={!m.total}>
                <strong>{m.verifiedPct}%</strong><span>verified</span>
              </Donut>
              <ul className="dx-legend">
                <li><i style={{ background: "#1ec99b" }} />Verified<b>{m.verified.toLocaleString("en-IN")}</b></li>
                <li><i style={{ background: "#cfd8df" }} />Waiting<b>{m.unverified.toLocaleString("en-IN")}</b></li>
              </ul>
            </div>
          </article>

          <article className="dx-panel" style={{ "--i": 2 }}>
            <h2>Melava payments</h2>
            <div className="dx-paybar" aria-hidden="true">
              <i className="paid" style={{ width: `${m.paidPct}%` }} />
              <i className="due" style={{ width: m.payTotal ? `${100 - m.paidPct}%` : "0%" }} />
            </div>
            <ul className="dx-legend wide">
              <li><i style={{ background: "#14b8a6" }} />Verified<b>{m.verifiedPay.toLocaleString("en-IN")}</b></li>
              <li><i style={{ background: "#f59e0b" }} />Pending<b>{m.pendingPay.toLocaleString("en-IN")}</b></li>
            </ul>
            <AppLink to={ROUTES.payments} className="dx-link">Review payments <ArrowUpRight size={14} aria-hidden="true" /></AppLink>
          </article>
        </section>

        {/* ---------- Recent + events ---------- */}
        <div className="dx-main">
          <section className="dx-panel" aria-label="Recent registrations" style={{ "--i": 3 }}>
            <div className="dx-panel-head">
              <h2>Recent registrations</h2>
              <div className="dx-chips" role="group" aria-label="Filter by type">
                {TYPE_FILTERS.map((f) => (
                  <button key={f.id} type="button" className={recentType === f.id ? "active" : ""}
                    aria-pressed={recentType === f.id} onClick={() => setRecentType(f.id)}>
                    {f.label}
                  </button>
                ))}
              </div>
            </div>
            <ul className="dx-people">
              {recent.map((p, i) => <RecentRow key={p.id ?? i} profile={p} index={i} />)}
            </ul>
            {!recent.length ? (
              <p className="dx-empty">{stats ? "No registrations match this view yet." : "Loading registrations…"}</p>
            ) : null}
            <AppLink to={ROUTES.profiles} className="dx-link">View all profiles <ArrowUpRight size={14} aria-hidden="true" /></AppLink>
          </section>

          <section className="dx-panel" aria-label="Upcoming Melava events" style={{ "--i": 4 }}>
            <div className="dx-panel-head">
              <h2>Upcoming Melava events</h2>
              <AppLink to={ROUTES.events} className="dx-link inline">View all</AppLink>
            </div>
            <div className="dx-events">
              {events.map((e, i) => <EventCard key={e.id ?? i} event={e} index={i} />)}
            </div>
            {!events.length ? (
              <p className="dx-empty">{stats ? "No upcoming Melava events." : "Loading events…"}</p>
            ) : null}
          </section>
        </div>
      </div>
    </AdminLayout>
  );
}