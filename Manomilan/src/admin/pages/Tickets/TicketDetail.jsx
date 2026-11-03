import { useEffect, useState } from "react";
import {
  ArrowLeft, CalendarDays, CheckCircle2, Download, FileText, Pencil,
} from "lucide-react";
import { useLocation, useNavigate } from "react-router-dom";
import AdminLayout from "../../components/AdminLayout";
import { useAdminPermission } from "../../../permission-context";
import { getTicket } from "./api/ticketApi";
import "./tickets.css";

const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("en-IN", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
    timeZone: "Asia/Kolkata",
  }).format(date);
};

const statusClass = (status) => {
  switch (status) {
    case "Open": return "open";
    case "In Progress": return "progress";
    case "Resolved": return "resolved";
    default: return "closed";
  }
};

function AttachmentLink({ url, name = "View attachment" }) {
  if (!url) return <span className="tk-detail-muted">—</span>;
  return (
    <a className="tk-detail-attachment" href={url} target="_blank" rel="noreferrer">
      <Download size={15} />
      <span>{name}</span>
    </a>
  );
}

function StatusPill({ status }) {
  const value = status || "Open";
  return (
    <span className={`tk-detail-status-pill tk-detail-status-${statusClass(value)}`}>
      <span className="tk-detail-status-dot" />
      {value}
    </span>
  );
}

function SummaryCard({ label, value, icon: Icon, children }) {
  return (
    <section className="tk-detail-summary-card">
      <span className="tk-detail-summary-icon"><Icon size={20} /></span>
      <div className="tk-detail-summary-content">
        <span className="tk-detail-label">{label}</span>
        {children || <strong>{value || "—"}</strong>}
      </div>
    </section>
  );
}

export default function TicketDetail({ params = {} }) {
  const location = useLocation();
  const navigate = useNavigate();
  const canEditTicket = useAdminPermission("edit", "/admin/software-tickets");
  const [ticket, setTicket] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [retryCount, setRetryCount] = useState(0);

  useEffect(() => {
    let active = true;
    getTicket(params.id)
      .then((response) => {
        if (active) setTicket(response?.data || null);
      })
      .catch((loadError) => {
        if (active) setError(loadError.message || "Unable to load ticket details.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [params.id, retryCount]);

  const retryLoading = () => {
    setTicket(null);
    setError("");
    setLoading(true);
    setRetryCount((count) => count + 1);
  };

  const items = Array.isArray(ticket?.items) ? ticket.items : [];
  const canEdit = ticket?.ticket_status === "Open" && Number(ticket?.status) === 1;

  return (
    <AdminLayout currentPath="/admin/software-tickets">
      <main className="tk-page tk-detail-page">
        <header className="tk-detail-header">
          <div className="tk-detail-heading">
            <span className="tk-detail-ticket-icon"><FileText size={22} /></span>
            <div>
              <span className="tk-detail-eyebrow">Ticket Details</span>
              <h1>{ticket?.ticket_no || (loading ? "Loading ticket…" : "Ticket")}</h1>
            </div>
          </div>
          <div className="tk-detail-header-actions">
            <button
              type="button"
              className="tk-detail-back"
              onClick={() => navigate(location.state?.from || "/admin/software-tickets")}
            >
              <ArrowLeft size={16} /> Back to Tickets
            </button>
            {canEditTicket ? <button
              type="button"
              className="tk-detail-edit"
              disabled={!canEdit}
              onClick={() => navigate(`/admin/software-tickets/${ticket.id}/edit`)}
              title={canEdit ? "Edit ticket" : "Only open active tickets can be edited"}
            >
              <Pencil size={15} /> Edit Ticket
            </button> : null}
          </div>
        </header>

        <section className="tk-detail-card">
          <div className="tk-detail-body">
            {error ? <div className="tk-alert tk-alert-error" role="alert">{error}</div> : null}
            {location.state?.notice ? (
              <div className="tk-alert tk-alert-success" role="status">{location.state.notice}</div>
            ) : null}
            {loading ? (
              <div className="tk-detail-state">Loading ticket details…</div>
            ) : error ? (
              <div className="tk-detail-state">
                <button type="button" className="tk-detail-retry" onClick={retryLoading}>
                  Try again
                </button>
              </div>
            ) : ticket ? (
              <>
                <div className="tk-detail-summary-grid">
                  <SummaryCard label="Module" value={ticket.module?.module_name} icon={FileText} />
                  <SummaryCard label="Subject" value={ticket.subject} icon={Pencil} />
                  <SummaryCard label="Category" value={ticket.category} icon={FileText} />
                  <SummaryCard label="Status" icon={CheckCircle2}>
                    <StatusPill status={ticket.ticket_status} />
                  </SummaryCard>
                </div>

                <div className="tk-detail-meta">
                  <span><CalendarDays size={15} /> Created {formatDate(ticket.created_at)}</span>
                  <span className="tk-detail-ticket-reference">Ticket No: <strong>{ticket.ticket_no}</strong></span>
                </div>

                {ticket.description ? (
                  <section className="tk-detail-description">
                    <h2>Common Description</h2>
                    <p>{ticket.description}</p>
                    <AttachmentLink url={ticket.attachment_url} name="Common attachment" />
                  </section>
                ) : ticket.attachment_url ? (
                  <section className="tk-detail-description">
                    <h2>Common Attachment</h2>
                    <AttachmentLink url={ticket.attachment_url} name="View common attachment" />
                  </section>
                ) : null}

                <section className="tk-detail-items">
                  <div className="tk-detail-section-heading">
                    <div>
                      <h2>Ticket Items</h2>
                      <span>{items.length} {items.length === 1 ? "item" : "items"}</span>
                    </div>
                  </div>
                  {items.length ? (
                    <div className="tk-detail-table-scroll">
                      <table className="tk-detail-table">
                        <thead>
                          <tr>
                            <th>Sr. No</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Priority</th>
                            <th>Severity</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Attachment</th>
                          </tr>
                        </thead>
                        <tbody>
                          {items.map((item, index) => (
                            <tr key={item.id || `${item.title}-${index}`}>
                              <td className="tk-detail-row-number">{index + 1}</td>
                              <td className="tk-detail-item-title">{item.title || "—"}</td>
                              <td className="tk-detail-item-description">{item.description || "—"}</td>
                              <td><span className="tk-detail-value-badge">{item.priority || "—"}</span></td>
                              <td><span className="tk-detail-value-badge">{item.severity || "—"}</span></td>
                              <td><span className="tk-detail-value-badge">{item.item_type || "—"}</span></td>
                              <td>
                                <StatusPill status={item.ticket_status} />
                              </td>
                              <td><AttachmentLink url={item.attachment_url} /></td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : (
                    <div className="tk-detail-empty">No ticket items are available.</div>
                  )}
                </section>
              </>
            ) : (
              <div className="tk-detail-state">Ticket details are unavailable.</div>
            )}
          </div>
        </section>
      </main>
    </AdminLayout>
  );
}
