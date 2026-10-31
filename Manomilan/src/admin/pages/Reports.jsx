import { useCallback, useEffect, useMemo, useState } from "react";
import { Download, FileText, RefreshCw } from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { getAdminDashboard } from "../../auth";
import "./reports.css";

const PERIODS = [
  { value: "today", label: "Today" },
  { value: "week", label: "This week" },
  { value: "all", label: "All time" },
  { value: "custom", label: "Custom range" },
];

const REPORTS = [
  ["total_users", "Accounts created"],
  ["total_profiles", "Active profiles"],
  ["verified_profiles", "Verified profiles"],
  ["pending_profiles", "Profiles pending verification"],
  ["groom_profiles", "Groom profiles"],
  ["bride_profiles", "Bride profiles"],
  ["melava_campaigns", "Melava events"],
  ["melava_registrations", "Melava registrations"],
  ["verified_melava_payments", "Verified payments"],
  ["pending_melava_payments", "Payments pending verification"],
];

const csvCell = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;

function exportReport(data, period, from, to) {
  const rows = [
    ["Manomilan Admin Report"],
    ["Period", period === "custom" ? `${from} to ${to}` : period],
    ["Generated at", new Date().toLocaleString("en-IN")],
    [],
    ["Metric", "Count"],
    ...REPORTS.map(([key, label]) => [label, Number(data[key] || 0)]),
  ];
  const csv = rows.map((row) => row.map(csvCell).join(",")).join("\r\n");
  const url = URL.createObjectURL(new Blob(["\ufeff", csv], { type: "text/csv;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = `manomilan-admin-report-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

export default function Reports() {
  const [period, setPeriod] = useState("all");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [data, setData] = useState(null);
  const [generatedAt, setGeneratedAt] = useState(null);
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
      getAdminDashboard(
        period === "custom"
          ? { force: true, from, to }
          : { force: true, period },
      )
        .then((report) => {
          if (!active) return;
          setData(report);
          setError("");
          setGeneratedAt(new Date());
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
  }, [period, from, to, rangeReady, refreshKey]);

  const reportRows = useMemo(
    () => REPORTS.map(([key, label]) => ({
      key,
      label,
      value: Number(data?.[key] || 0),
    })),
    [data],
  );

  return (
    <AdminLayout currentPath="/admin/report">
      <div className="ar-page">
        <header className="ar-heading">
          <div>
            <h1>Reports</h1>
            <p>Review live account, profile, event, and payment totals for a selected period.</p>
          </div>
          <div className="ar-actions">
            <button type="button" className="ar-button" onClick={refresh} disabled={loading}>
              <RefreshCw size={16} className={loading ? "ar-spin" : ""} />
              Refresh
            </button>
            <button type="button" className="ar-button primary" onClick={() => exportReport(data, period, from, to)} disabled={!data || loading}>
              <Download size={16} />
              Export CSV
            </button>
          </div>
        </header>

        <section className="ar-panel ar-toolbar" aria-label="Report filters">
          <div className="ar-periods" role="group" aria-label="Report period">
            {PERIODS.map((item) => (
              <button
                type="button"
                key={item.value}
                className={period === item.value ? "active" : ""}
                aria-pressed={period === item.value}
                onClick={() => setPeriod(item.value)}
              >
                {item.label}
              </button>
            ))}
          </div>
          {period === "custom" ? (
            <div className="ar-dates">
              <label>
                From
                <input type="date" value={from} max={to || undefined} onChange={(event) => setFrom(event.target.value)} />
              </label>
              <label>
                To
                <input type="date" value={to} min={from || undefined} onChange={(event) => setTo(event.target.value)} />
              </label>
            </div>
          ) : null}
          <small>{generatedAt ? `Updated ${generatedAt.toLocaleTimeString("en-IN", { hour: "numeric", minute: "2-digit" })}` : "Totals are loaded from the Admin API."}</small>
        </section>

        {period === "custom" && from && to && from > to ? (
          <div className="ar-error" role="alert">The start date must be on or before the end date.</div>
        ) : null}
        {period === "custom" && (!from || !to) ? (
          <div className="ar-empty">Choose both dates to generate a report.</div>
        ) : null}
        {error ? (
          <div className="ar-error" role="alert">
            <span>{error}</span>
            <button type="button" onClick={refresh}>Try again</button>
          </div>
        ) : null}

        <section className={`ar-panel ${loading ? "is-loading" : ""}`} aria-labelledby="ar-summary-heading">
          <div className="ar-panel-heading">
            <div className="ar-heading-icon"><FileText size={18} /></div>
            <div>
              <h2 id="ar-summary-heading">Summary</h2>
              <p>Counts returned by the existing Admin dashboard endpoint.</p>
            </div>
          </div>
          <div className="ar-grid">
            {reportRows.map((row) => (
              <article className="ar-metric" key={row.key}>
                <span>{row.label}</span>
                <strong>{data ? row.value.toLocaleString("en-IN") : loading ? "—" : "0"}</strong>
              </article>
            ))}
          </div>
          {!loading && !data && !error ? <div className="ar-empty">No report data is available.</div> : null}
        </section>
      </div>
    </AdminLayout>
  );
}
