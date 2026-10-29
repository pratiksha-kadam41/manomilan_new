import { useCallback, useEffect, useMemo, useState } from "react";
import { BadgeCheck, CheckCircle2, Clock, Eye, RefreshCw, Search, XCircle } from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { request } from "../../api";
import "./melavaPayments.css";

const STATUS = {
  pending: { label: "Pending", icon: Clock },
  verified: { label: "Verified", icon: CheckCircle2 },
};

const fetchRegistrations = () =>
  request("/melava-registrations?per_page=100");

const setPaymentStatus = (id, verified) =>
  request(`/melava-registrations/${id}/payment-status`, {
    method: "PUT",
    body: { payment_verified: verified },
  });

const formatDate = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
};

export default function MelavaPayments() {
  const [registrations, setRegistrations] = useState([]);
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("all");
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState("");
  const [toast, setToast] = useState(null);

  const showToast = (type, text) => {
    setToast({ type, text });
    window.setTimeout(() => setToast(null), 3200);
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await fetchRegistrations();
      const rows = response?.data?.data || response?.data || [];
      setRegistrations(Array.isArray(rows) ? rows : []);
    } catch (err) {
      setError(err.message || "Unable to load Melava registrations.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const rows = useMemo(() => {
    const search = query.trim().toLowerCase();
    return registrations.filter((row) => {
      if (filter === "pending" && row.payment_verified) return false;
      if (filter === "verified" && !row.payment_verified) return false;
      if (!search) return true;
      return [row.fullName, row.username, row.profile?.serial_no]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(search));
    });
  }, [registrations, query, filter]);

  const updateStatus = async (row, verified) => {
    if (busyId) return;
    setBusyId(row.id);
    try {
      await setPaymentStatus(row.id, verified);
      setRegistrations((previous) =>
        previous.map((item) =>
          item.id === row.id ? { ...item, payment_verified: verified } : item,
        ),
      );
      showToast("success", verified ? "Payment marked as verified." : "Payment marked as pending.");
    } catch (err) {
      showToast("error", err.message || "Unable to update payment status.");
    } finally {
      setBusyId(null);
    }
  };

  const pendingCount = registrations.filter((row) => !row.payment_verified).length;

  return (
    <AdminLayout currentPath="/admin/melava">
      <div className="mp-page">
        <div className="mp-head">
          <div>
            <h1>Melava Payments</h1>
            <p>Review uploaded payment screenshots and verify registrations.</p>
          </div>
          <button type="button" className="mp-btn mp-btn-ghost" onClick={load} disabled={loading}>
            <RefreshCw size={16} className={loading ? "mp-spin" : ""} /> Refresh
          </button>
        </div>

        <div className="mp-stats">
          <div className="mp-stat">
            <span>Total registrations</span>
            <strong>{registrations.length}</strong>
          </div>
          <div className="mp-stat pending">
            <span>Pending payment verification</span>
            <strong>{pendingCount}</strong>
          </div>
          <div className="mp-stat verified">
            <span>Verified payments</span>
            <strong>{registrations.length - pendingCount}</strong>
          </div>
        </div>

        <div className="mp-tools">
          <label className="mp-search">
            <Search size={17} />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search name, mobile or serial number"
            />
          </label>
          <div className="mp-tabs">
            {[
              ["all", "All"],
              ["pending", "Pending"],
              ["verified", "Verified"],
            ].map(([value, label]) => (
              <button
                key={value}
                type="button"
                className={filter === value ? "on" : ""}
                onClick={() => setFilter(value)}
              >
                {label}
              </button>
            ))}
          </div>
        </div>

        {error ? <div className="mp-alert er">{error}</div> : null}

        <div className="mp-card">
          <div className="mp-row mp-row-head">
            <span>Candidate</span>
            <span>Mobile</span>
            <span>Serial</span>
            <span>Screenshot</span>
            <span>Payment</span>
            <span>Action</span>
          </div>

          {loading ? (
            Array.from({ length: 5 }, (_, index) => (
              <div className="mp-row mp-skeleton" key={index}><span /><span /><span /><span /><span /><span /></div>
            ))
          ) : null}

          {!loading && !rows.length ? (
            <div className="mp-empty">
              <BadgeCheck size={34} />
              <p>No Melava registrations match this view.</p>
            </div>
          ) : null}

          {!loading && rows.map((row) => {
            const verified = Boolean(row.payment_verified);
            const status = verified ? STATUS.verified : STATUS.pending;
            const StatusIcon = status.icon;
            return (
              <div className={`mp-row ${verified ? "verified" : ""}`} key={row.id}>
                <div className="mp-person">
                  <strong>{row.fullName || row.profile?.fullName || "Unnamed candidate"}</strong>
                  <small>
                    {row.melava_title || "Melava"} · {formatDate(row.melava_registered_at || row.created_at)}
                    {row.is_booklet_req ? " | Booklet" : ""}
                  </small>
                </div>
                <div>{row.username || row.profile?.phone || "-"}</div>
                <div>{row.profile?.serial_no || "-"}</div>
                <div>
                  {row.payment_screenshot_url ? (
                    <a
                      className="mp-view"
                      href={row.payment_screenshot_url}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <Eye size={16} /> View
                    </a>
                  ) : (
                    <span className="mp-muted">Not uploaded</span>
                  )}
                </div>
                <div>
                  <span className={`mp-status ${verified ? "verified" : "pending"}`}>
                    <StatusIcon size={14} /> {status.label}
                  </span>
                  {row.total_amount !== null && row.total_amount !== undefined ? (
                    <small className="mp-muted">{Number(row.total_amount).toLocaleString("en-IN", {
                      style: "currency",
                      currency: "INR",
                      maximumFractionDigits: 2,
                    })}</small>
                  ) : null}
                </div>
                <div>
                  <button
                    type="button"
                    className={`mp-btn ${verified ? "mp-btn-ghost" : "mp-btn-green"}`}
                    disabled={busyId === row.id}
                    onClick={() => updateStatus(row, !verified)}
                  >
                    {verified ? <XCircle size={15} /> : <CheckCircle2 size={15} />}
                    {busyId === row.id ? "Saving..." : verified ? "Mark pending" : "Verify"}
                  </button>
                </div>
              </div>
            );
          })}
        </div>

        {toast ? <div className={`mp-toast ${toast.type}`}>{toast.text}</div> : null}
      </div>
    </AdminLayout>
  );
}
