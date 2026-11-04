import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Activity, BadgeCheck, CalendarDays, CheckCircle2, Clock3, Download, FileText,
  Heart, MapPin, RefreshCw, Ticket, UserCheck, Users, Wallet, Printer,
} from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { getAdminDashboard } from "../../auth";
import { PermissionGate } from "../../permission-context";
import "../components/admin-ui.css";
import "./reports.css";

const PERIODS = [
  { value: "today", label: "Today" },
  { value: "week", label: "This week" },
  { value: "month", label: "This month" },
  { value: "all", label: "All time" },
  { value: "custom", label: "Date range" },
];

const TABS = [
  ["overview", "Overview"],
  ["registration", "Registration"],
  ["verification", "Verification"],
  ["payments", "Payments"],
  ["events", "Melava & Events"],
  ["location", "Location"],
  ["quality", "Profile Quality"],
  ["activity", "User Activity"],
];

const number = new Intl.NumberFormat("en-IN");
const csvCell = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;

const displayDate = (value) => {
  if (!value) return "Undated";
  const date = new Date(`${value}T00:00:00`);
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat("en-IN", { day: "numeric", month: "short", year: "numeric" }).format(date);
};

function exportCsv(data, filters) {
  const rows = [
    ["Manomilan report"],
    ["Generated at", data.generated_at || new Date().toISOString()],
    ["Period", filters.period === "custom" ? `${filters.from} to ${filters.to}` : filters.period],
    ["Gender", filters.gender],
    ["Profile status", filters.status],
    ["Source", filters.source || "All"],
    [],
    ["Summary metric", "Value"],
    ["Accounts created", data.total_users],
    ["Profiles", data.total_profiles],
    ["Active profiles", data.active_profiles],
    ["Inactive profiles", data.inactive_profiles],
    ["Verified profiles", data.verified_profiles],
    ["Pending verification", data.pending_profiles],
    ["Groom profiles", data.groom_profiles],
    ["Bride profiles", data.bride_profiles],
    ["Melava events", data.melava_campaigns],
    ["Melava registrations", data.melava_registrations],
    ["Verified Melava payments", data.verified_melava_payments],
    ["Pending Melava payments", data.pending_melava_payments],
    ["Verified booklet payments", data.verified_booklet_payments],
    ["Pending booklet payments", data.pending_booklet_payments],
    ["Workshop events", data.workshop_events],
    ["Workshop registrations", data.workshop_registrations],
    ["Verified workshop registrations", data.verified_workshop_registrations],
    ["Pending workshop registrations", data.pending_workshop_registrations],
    ["Rejected workshop registrations", data.rejected_workshop_registrations],
    ["Payment entries", data.payments?.entries],
    [],
    ["Registration trend", "Profiles", "Grooms", "Brides"],
    ...(data.trend || []).map((item) => [item.label, item.profiles, item.grooms, item.brides]),
    [],
    ["Top locations", "Profiles"],
    ...(data.locations || []).map((item) => [item.label, item.count]),
    [],
    ["Melava event", "Code", "Start date", "End date", "Status", "Registrations"],
    ...(data.melava_event_details || []).map((item) => [
      item.title, item.code, item.start_date, item.end_date, item.status, item.registrations,
    ]),
    [],
    ["Workshop event", "Code", "Start date", "End date", "Status", "Registrations"],
    ...(data.workshop_event_details || []).map((item) => [
      item.title, item.code, item.start_date, item.end_date, item.status, item.registrations,
    ]),
  ];
  const csv = rows.map((row) => row.map(csvCell).join(",")).join("\r\n");
  const url = URL.createObjectURL(new Blob(["\ufeff", csv], { type: "text/csv;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = `manomilan-report-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

function MetricCard({ label, value, icon: Icon, tone = "blue", hint }) {
  return (
    <article className={`ar-metric ar-tone-${tone}`}>
      <div className="ar-metric-copy">
        <span>{label}</span>
        <strong>{value === null || value === undefined ? "—" : typeof value === "number" ? number.format(value) : value}</strong>
        {hint ? <small>{hint}</small> : null}
      </div>
      <i><Icon size={19} aria-hidden="true" /></i>
    </article>
  );
}

function Panel({ title, icon: Icon, children, className = "" }) {
  return (
    <section className={`ar-panel ar-chart-panel ${className}`}>
      <div className="ar-panel-heading">
        <span className="ar-heading-icon"><Icon size={17} aria-hidden="true" /></span>
        <h2>{title}</h2>
      </div>
      {children}
    </section>
  );
}

function Donut({ title, icon, items, total, className = "" }) {
  const colors = ["#20b486", "#ffbd4a", "#9aa7bc", "#ef4e92"];
  const sum = items.reduce((result, item) => result + Number(item.count || 0), 0);
  let offset = 0;
  const stops = items.map((item, index) => {
    const start = offset;
    offset += sum ? (Number(item.count || 0) / sum) * 100 : 0;
    return `${colors[index % colors.length]} ${start}% ${offset}%`;
  });
  const background = sum ? `conic-gradient(${stops.join(", ")})` : "#e8edf3";

  return (
    <Panel title={title} icon={icon} className={className}>
      <div className="ar-donut-layout">
        <div className="ar-donut" style={{ background }} role="img" aria-label={`${title}: ${number.format(total)} total`}>
          <div><strong>{number.format(total)}</strong><span>Profiles</span></div>
        </div>
        <ul className="ar-legend">
          {items.map((item, index) => {
            const count = Number(item.count || 0);
            const percent = total ? (count / total) * 100 : 0;
            return (
              <li key={item.label}>
                <i style={{ backgroundColor: colors[index % colors.length] }} />
                <span>{item.label}</span>
                <strong>{number.format(count)} <small>({percent.toFixed(1)}%)</small></strong>
              </li>
            );
          })}
        </ul>
      </div>
    </Panel>
  );
}

function TrendChart({ rows }) {
  const width = 720;
  const height = 220;
  const padding = { top: 16, right: 12, bottom: 34, left: 38 };
  const plotWidth = width - padding.left - padding.right;
  const plotHeight = height - padding.top - padding.bottom;
  const max = Math.max(1, ...(rows || []).flatMap((row) => [row.profiles, row.grooms, row.brides].map(Number)));
  const x = (index) => padding.left + ((rows.length <= 1 ? 0.5 : index / (rows.length - 1)) * plotWidth);
  const y = (value) => padding.top + plotHeight - ((Number(value || 0) / max) * plotHeight);
  const makePoints = (key) => rows.map((row, index) => `${x(index)},${y(row[key])}`).join(" ");
  const labelsToShow = rows.length > 10
    ? rows.filter((_, index) => index % Math.ceil(rows.length / 8) === 0 || index === rows.length - 1)
    : rows;

  if (!rows.length) return <p className="ar-empty">No registration trend data for this period.</p>;
  return (
    <div className="ar-trend-wrap">
      <div className="ar-chart-legend">
        <span><i className="ar-dot profiles" />Profiles</span>
        <span><i className="ar-dot groom" />Groom</span>
        <span><i className="ar-dot bride" />Bride</span>
      </div>
      <svg className="ar-trend" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Profile registrations over the selected period">
        {[0, 1, 2, 3, 4].map((step) => {
          const value = Math.round(max * (4 - step) / 4);
          const lineY = padding.top + (plotHeight * step / 4);
          return (
            <g key={step}>
              <line x1={padding.left} x2={width - padding.right} y1={lineY} y2={lineY} className="ar-gridline" />
              <text x={padding.left - 7} y={lineY + 4} textAnchor="end" className="ar-axis-label">{value}</text>
            </g>
          );
        })}
        <polyline points={makePoints("profiles")} className="ar-line profiles" />
        <polyline points={makePoints("grooms")} className="ar-line groom" />
        <polyline points={makePoints("brides")} className="ar-line bride" />
        {labelsToShow.map((row) => {
          const index = rows.indexOf(row);
          return <text key={row.key} x={x(index)} y={height - 8} textAnchor="middle" className="ar-axis-label">{row.label}</text>;
        })}
      </svg>
    </div>
  );
}

function BarList({ items, valueLabel = "profiles", formatValue = (value) => number.format(value), empty = "No data available." }) {
  const max = Math.max(1, ...items.map((item) => Number(item.count || 0)));
  if (!items.length) return <p className="ar-empty">{empty}</p>;
  return (
    <div className="ar-bars">
      {items.map((item) => (
        <div className="ar-bar-row" key={item.label}>
          <span title={item.label}>{item.label}</span>
          <div className="ar-bar-track"><i style={{ width: `${Math.max(1, (item.count / max) * 100)}%` }} /></div>
          <strong>{formatValue(Number(item.count || 0))}</strong>
          <small>{valueLabel}</small>
        </div>
      ))}
    </div>
  );
}

function Funnel({ data }) {
  const rows = [
    { label: "Profiles created", count: data.total_profiles },
    { label: "Active profiles", count: data.active_profiles },
    { label: "Verified profiles", count: data.verified_profiles },
  ].filter((item) => item.count !== null);
  const max = Math.max(1, ...rows.map((item) => item.count));
  return (
    <div className="ar-funnel">
      {rows.map((row, index) => (
        <div className={`ar-funnel-row ar-funnel-${index}`} key={row.label}>
          <strong>{number.format(row.count)}</strong><span>{row.label}</span>
          <i style={{ width: `${Math.max(46, (row.count / max) * 100)}%` }} />
        </div>
      ))}
    </div>
  );
}

function EventTable({ events, kind }) {
  if (!events.length) return <p className="ar-empty">No {kind} event records were found for this filter.</p>;
  return (
    <div className="ar-table-scroll">
      <table className="ar-table">
        <thead>
          <tr>
            <th>Event</th><th>Code</th><th>Start</th><th>End</th><th>Status</th><th>Registrations</th>
          </tr>
        </thead>
        <tbody>
          {events.map((event) => (
            <tr key={event.id}>
              <td>{event.title}</td>
              <td>{event.code || "—"}</td>
              <td>{displayDate(event.start_date)}</td>
              <td>{displayDate(event.end_date)}</td>
              <td>{event.status || "—"}</td>
              <td>{number.format(Number(event.registrations || 0))}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <span className="ar-table-caption">Showing up to 10 {kind} events, ordered by matching registrations.</span>
    </div>
  );
}

function PaymentSummary({
  payments,
  melavaVerified,
  melavaPending,
  bookletVerified,
  bookletPending,
  workshopVerified,
  workshopPending,
  workshopRejected,
}) {
  const items = [
    { label: "Verified payment entries", count: payments.verified_entries },
    { label: "Pending payment entries", count: payments.pending_entries },
    { label: "Verified Melava registrations", count: melavaVerified },
    { label: "Pending Melava registrations", count: melavaPending },
  ];
  const max = Math.max(1, ...items.map((item) => Number(item.count || 0)));
  return (
    <>
      <div className="ar-payment-bars">
        {items.map((item, index) => (
          <div key={item.label}>
            <span>{number.format(Number(item.count || 0))}</span>
            <i style={{ height: `${Math.max(4, (Number(item.count || 0) / max) * 100)}%` }} className={`ar-payment-bar-${index}`} />
            <small>{item.label}</small>
          </div>
        ))}
      </div>
      <div className="ar-summary-list ar-payment-extra">
        <div><span>Verified booklet payments</span><strong>{number.format(bookletVerified || 0)}</strong></div>
        <div><span>Pending booklet payments</span><strong>{number.format(bookletPending || 0)}</strong></div>
        <div><span>Verified workshop registrations</span><strong>{number.format(workshopVerified || 0)}</strong></div>
        <div><span>Pending workshop registrations</span><strong>{number.format(workshopPending || 0)}</strong></div>
        <div><span>Rejected workshop registrations</span><strong>{number.format(workshopRejected || 0)}</strong></div>
      </div>
    </>
  );
}

function Reports() {
  const [period, setPeriod] = useState("all");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [gender, setGender] = useState("all");
  const [status, setStatus] = useState("all");
  const [source, setSource] = useState("");
  const [tab, setTab] = useState("overview");
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);

  const rangeReady = period !== "custom" || Boolean(from && to && from <= to);
  const refresh = useCallback(() => setRefreshKey((key) => key + 1), []);

  useEffect(() => {
    if (!rangeReady) return undefined;
    let active = true;
    const timer = window.setTimeout(() => {
      setLoading(true);
      getAdminDashboard({
        force: true,
        period: period === "custom" ? "" : period,
        from: period === "custom" ? from : "",
        to: period === "custom" ? to : "",
        gender,
        status,
        source,
      })
        .then((report) => {
          if (!active) return;
          setData(report);
          setError("");
        })
        .catch((requestError) => {
          if (active) setError(requestError.message || "Unable to load report data.");
        })
        .finally(() => {
          if (active) setLoading(false);
        });
    }, 0);
    return () => {
      active = false;
      window.clearTimeout(timer);
    };
  }, [period, from, to, gender, status, source, rangeReady, refreshKey]);

  const metrics = useMemo(() => {
    if (!data) return [];
    return [
      ["Accounts created", data.total_users, Users, "teal"],
      ["Active profiles", data.active_profiles, UserCheck, "blue"],
      ["Verified profiles", data.verified_profiles, BadgeCheck, "green"],
      ["Pending verification", data.pending_profiles, Clock3, "amber"],
      ["Groom profiles", data.groom_profiles, UserCheck, "blue"],
      ["Bride profiles", data.bride_profiles, Heart, "pink"],
      ["Profile completion", data.profile_completion?.average === null || data.profile_completion?.average === undefined ? "—" : `${data.profile_completion.average}%`, CheckCircle2, "green", "average"],
      ["Payment entries", data.payments?.entries, Wallet, "blue"],
      ["Melava events", data.melava_campaigns, CalendarDays, "pink"],
      ["Melava registrations", data.melava_registrations, Ticket, "purple"],
      ["Workshop registrations", data.workshop_registrations, CalendarDays, "teal"],
      ["Booklet payment verifications", Number(data.verified_booklet_payments || 0) + Number(data.pending_booklet_payments || 0), Ticket, "purple"],
    ];
  }, [data]);

  const scopeText = data?.range?.from
    ? `${displayDate(data.range.from)} - ${displayDate(data.range.to)}`
    : "All available dates";
  const statusItems = data?.profile_status_distribution || [];
  const genderItems = data?.gender_distribution || [];
  const profileTotal = Number(data?.total_profiles || 0);
  const qualityBands = data?.profile_completion?.bands || [];
  const paymentData = data?.payments || {
    entries: 0, verified_entries: 0, pending_entries: 0,
  };
  const eventList = data?.melava_event_details || [];
  const workshopList = data?.workshop_event_details || [];

  const renderRegistration = () => (
    <>
      <Panel title="Registration Trend" icon={CalendarDays} className="ar-span-two">
        <TrendChart rows={data.trend || []} />
        <p className="ar-chart-caption">
          {data.trend_range
            ? `Profile creation by ${data.trend_range.granularity}; ${displayDate(data.trend_range.from)} - ${displayDate(data.trend_range.to)}.`
            : "Profile creation during the selected period."}
        </p>
      </Panel>
      <Donut title="Gender Distribution" icon={Heart} items={genderItems} total={profileTotal} />
      <Panel title="Registration Funnel" icon={UserCheck}>
        <Funnel data={data} />
      </Panel>
      <Panel title="Registration Sources" icon={Users}>
        <BarList items={data.sources || []} valueLabel="profiles" />
      </Panel>
    </>
  );

  const renderVerification = () => (
    <>
      <Donut title="Profile Status Distribution" icon={BadgeCheck} items={statusItems} total={profileTotal} className="ar-span-two" />
      <Panel title="Profile Completion" icon={CheckCircle2}>
        {data.profile_completion?.available ? (
          <>
            <div className="ar-quality-score"><strong>{data.profile_completion.average ?? 0}%</strong><span>Average stored completion score</span></div>
            <BarList items={qualityBands} valueLabel="profiles" />
          </>
        ) : <p className="ar-empty">Profile completion scores are unavailable. Apply the profile completion migration to enable this report.</p>}
      </Panel>
      <Panel title="Verification Summary" icon={Clock3}>
        <div className="ar-summary-list">
          <div><span>Verified active profiles</span><strong>{number.format(data.verified_profiles || 0)}</strong></div>
          <div><span>Pending active profiles</span><strong>{number.format(data.pending_profiles || 0)}</strong></div>
          <div><span>Inactive profiles</span><strong>{number.format(data.inactive_profiles || 0)}</strong></div>
          <div><span>Profiles without a payment ledger entry</span><strong>{number.format(Math.max(profileTotal - Number(data.profiles_with_payments || 0), 0))}</strong></div>
        </div>
      </Panel>
    </>
  );

  const renderPayments = () => (
    <>
      <Panel title="Payment Status & Recorded Amounts" icon={Wallet} className="ar-span-two">
        <PaymentSummary
          payments={paymentData}
          melavaVerified={data.verified_melava_payments}
          melavaPending={data.pending_melava_payments}
          bookletVerified={data.verified_booklet_payments}
          bookletPending={data.pending_booklet_payments}
          workshopVerified={data.verified_workshop_registrations}
          workshopPending={data.pending_workshop_registrations}
          workshopRejected={data.rejected_workshop_registrations}
        />
      </Panel>
      <Panel title="Melava Payment Verification" icon={Ticket}>
        <div className="ar-summary-list">
          <div><span>Verified registrations</span><strong>{number.format(data.verified_melava_payments || 0)}</strong></div>
          <div><span>Pending registrations</span><strong>{number.format(data.pending_melava_payments || 0)}</strong></div>
          <div><span>Verified booklet payments</span><strong>{number.format(data.verified_booklet_payments || 0)}</strong></div>
          <div><span>Pending booklet payments</span><strong>{number.format(data.pending_booklet_payments || 0)}</strong></div>
          <div><span>All payment entries</span><strong>{number.format(paymentData.entries || 0)}</strong></div>
        </div>
      </Panel>
    </>
  );

  const renderEvents = () => (
    <>
      <Panel title="Melava Events" icon={Ticket} className="ar-span-two">
        <EventTable events={eventList} kind="Melava" />
      </Panel>
      <Panel title="Workshop Events" icon={CalendarDays} className="ar-span-two">
        <EventTable events={workshopList} kind="workshop" />
        <p className="ar-chart-caption">Workshop registrations are independent records without a profile link in this database; only the date range can be applied to them.</p>
      </Panel>
    </>
  );

  const renderQuality = () => (
    <>
      <Panel title="Profile Completion Distribution" icon={CheckCircle2} className="ar-span-two">
        {data.profile_completion?.available ? (
          <>
            <div className="ar-quality-score"><strong>{data.profile_completion.average ?? 0}%</strong><span>Average stored completion score</span></div>
            <BarList items={qualityBands} valueLabel="profiles" />
          </>
        ) : <p className="ar-empty">Profile completion scores are unavailable. Apply the profile completion migration to enable this report.</p>}
      </Panel>
      <Panel title="Profiles Requiring Completion" icon={Clock3}>
        <div className="ar-big-number">{data.profile_completion?.available ? number.format(data.profile_completion.incomplete) : "—"}</div>
        <p className="ar-explainer">Profiles with a stored completion score below 100 in the selected cohort.</p>
        <div className="ar-summary-list ar-quality-coverage">
          <div><span>Not scored</span><strong>{number.format(data.profile_completion?.not_scored || 0)}</strong></div>
        </div>
      </Panel>
      <Panel title="Completion Data Definition" icon={FileText}>
        <p className="ar-explainer">These figures use the saved <code>profile_completion</code> score. A missing score is not assumed to be complete, and the report shows when the score field is unavailable.</p>
      </Panel>
    </>
  );

  const renderActivity = () => (
    <>
      <Panel title="User Activity" icon={Activity} className="ar-span-two">
        <div className="ar-activity-grid">
          <div><span>Profile views</span><strong>{number.format(data.activity?.profile_views || 0)}</strong></div>
          <div><span>Profiles viewed</span><strong>{number.format(data.activity?.unique_profiles_viewed || 0)}</strong></div>
          <div><span>Interests received</span><strong>{number.format(data.activity?.interests || 0)}</strong></div>
          <div><span>Pending interests</span><strong>{number.format(data.activity?.pending_interests || 0)}</strong></div>
        </div>
      </Panel>
      <Panel title="Activity Scope" icon={FileText}>
        <p className="ar-explainer">Views are counted by the activity record date and filtered to profiles in this report. Interests are counted as received by those profiles; pending is based on the saved interest status.</p>
      </Panel>
    </>
  );

  const renderContent = () => {
    if (!data) return null;
    if (tab === "registration") return renderRegistration();
    if (tab === "verification") return renderVerification();
    if (tab === "payments") return renderPayments();
    if (tab === "events") return renderEvents();
    if (tab === "quality") return renderQuality();
    if (tab === "activity") return renderActivity();
    if (tab === "location") {
      return <Panel title="Top Locations (Profiles)" icon={MapPin} className="ar-span-two"><BarList items={data.locations || []} valueLabel="profiles" empty="No profile locations are available in these records." /></Panel>;
    }
    return (
      <>
        {renderRegistration()}
        <Donut title="Profile Status Distribution" icon={BadgeCheck} items={statusItems} total={profileTotal} />
        <Panel title="Payment Status" icon={Wallet}>
          <PaymentSummary
            payments={paymentData}
            melavaVerified={data.verified_melava_payments}
            melavaPending={data.pending_melava_payments}
            bookletVerified={data.verified_booklet_payments}
            bookletPending={data.pending_booklet_payments}
            workshopVerified={data.verified_workshop_registrations}
            workshopPending={data.pending_workshop_registrations}
            workshopRejected={data.rejected_workshop_registrations}
          />
        </Panel>
        <Panel title="Top Locations (Profiles)" icon={MapPin}>
          <BarList items={data.locations || []} valueLabel="profiles" empty="No profile locations are available in these records." />
        </Panel>
        <Panel title="Melava & Workshop" icon={Ticket}>
          <div className="ar-summary-list">
            <div><span>Melava events</span><strong>{number.format(data.melava_campaigns || 0)}</strong></div>
            <div><span>Melava registrations</span><strong>{number.format(data.melava_registrations || 0)}</strong></div>
            <div><span>Workshop events</span><strong>{number.format(data.workshop_events || 0)}</strong></div>
            <div><span>Workshop registrations</span><strong>{number.format(data.workshop_registrations || 0)}</strong></div>
          </div>
        </Panel>
        <Panel title="Quick Actions" icon={Download}>
          <div className="ar-quick-actions">
            <button type="button" onClick={() => window.print()}><FileText size={16} /> Download / print summary (PDF)</button>
            <PermissionGate permission="export" currentPath="/admin/report">
              <button type="button" onClick={() => exportCsv(data, { period, from, to, gender, status, source })}>
                <Download size={16} /> Export filtered data (CSV)
              </button>
            </PermissionGate>
            <button type="button" onClick={() => window.print()}><Printer size={16} /> Print report</button>
          </div>
        </Panel>
      </>
    );
  };

  return (
    <AdminLayout currentPath="/admin/report">
      <div className="ar-page adm-ui">
        <header className="ar-heading">
          <div>
            <h1>Reports</h1>
            <p>Comprehensive analytics for accounts, profiles, verification, payments, events, and activity.</p>
          </div>
          <div className="ar-actions">
            <button type="button" className="ar-button" onClick={refresh} disabled={loading}>
              <RefreshCw size={16} className={loading ? "ar-spin" : ""} /> Refresh
            </button>
            <PermissionGate permission="export" currentPath="/admin/report">
              <button
                type="button"
                className="ar-button primary"
                onClick={() => data && exportCsv(data, { period, from, to, gender, status, source })}
                disabled={!data || loading}
              >
                <Download size={16} /> Export
              </button>
            </PermissionGate>
          </div>
        </header>

        <nav className="ar-tabs" role="tablist" aria-label="Report categories">
          {TABS.map(([id, label]) => (
            <button key={id} type="button" role="tab" aria-selected={tab === id} className={tab === id ? "active" : ""} onClick={() => setTab(id)}>
              {label}
            </button>
          ))}
        </nav>

        <section className="ar-panel ar-toolbar" aria-label="Report filters">
          <div className="ar-periods" role="group" aria-label="Report period">
            {PERIODS.map((item) => (
              <button type="button" key={item.value} className={period === item.value ? "active" : ""} aria-pressed={period === item.value} onClick={() => setPeriod(item.value)}>
                {item.label}
              </button>
            ))}
          </div>
          {period === "custom" ? (
            <div className="ar-dates">
              <label>From<input type="date" value={from} max={to || undefined} onChange={(event) => setFrom(event.target.value)} /></label>
              <label>To<input type="date" value={to} min={from || undefined} onChange={(event) => setTo(event.target.value)} /></label>
            </div>
          ) : null}
          <label className="ar-select">Gender
            <select value={gender} onChange={(event) => setGender(event.target.value)}>
              <option value="all">All</option><option value="Groom">Groom</option><option value="Bride">Bride</option>
            </select>
          </label>
          <label className="ar-select">Status
            <select value={status} onChange={(event) => setStatus(event.target.value)}>
              <option value="all">All</option><option value="active">Active</option><option value="deactive">Inactive</option>
              <option value="verified">Verified</option><option value="pending">Pending verification</option>
            </select>
          </label>
          <label className="ar-select">Source
            <select value={source} onChange={(event) => setSource(event.target.value)}>
              <option value="">All</option>
              {(data?.sources || []).map((item) => <option value={item.label} key={item.label}>{item.label}</option>)}
            </select>
          </label>
          <small>{loading ? "Updating report…" : `Updated ${data?.generated_at ? new Date(data.generated_at).toLocaleTimeString("en-IN", { hour: "numeric", minute: "2-digit" }) : "—"} · ${scopeText}`}</small>
        </section>

        {period === "custom" && (!from || !to) ? <div className="ar-empty">Choose both dates to generate a report.</div> : null}
        {period === "custom" && from && to && from > to ? <div className="ar-error" role="alert">The start date must be on or before the end date.</div> : null}
        {error ? <div className="ar-error" role="alert"><span>{error}</span><button type="button" onClick={refresh}>Try again</button></div> : null}

        {data ? (
          <>
            <section className={`ar-kpis${loading ? " is-loading" : ""}`} aria-label="Report summary metrics">
              {metrics.map(([label, value, icon, tone, hint]) => (
                <MetricCard key={label} label={label} value={value} icon={icon} tone={tone} hint={hint} />
              ))}
            </section>
            <section className={`ar-report-grid${loading ? " is-loading" : ""}`} aria-live="polite">
              {renderContent()}
            </section>
          </>
        ) : !error && !loading && rangeReady ? <div className="ar-empty">No report data is available.</div> : null}
      </div>
    </AdminLayout>
  );
}

export default Reports;
