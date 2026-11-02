import { useCallback, useEffect, useRef, useState } from "react";
import {
  Archive, ArrowLeft, CheckCircle2, Clock3, Eye, Pencil, PlusCircle,
  RotateCcw, Search, Ticket, Trash2,
} from "lucide-react";
import { useLocation, useNavigate } from "react-router-dom";
import AdminLayout from "../../components/AdminLayout";
import useDebouncedValue from "../../hooks/useDebouncedValue";
import {
  activateTicket, deactivateTicket, listTickets,
} from "./api/ticketApi";
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

function StatusTimeline({ history = [] }) {
  const firstCreatedIndex = history.findIndex((entry) =>
    String(entry.remarks || "").toLowerCase().includes("created"));
  const visibleHistory = history.filter((entry, index) =>
    !String(entry.remarks || "").toLowerCase().includes("created") || index === firstCreatedIndex);

  return (
    <div className="tk-timeline">
      {visibleHistory.map((entry) => {
        const status = entry.new_status || "Open";
        const label = String(entry.remarks || "").toLowerCase().includes("created")
          ? "Created"
          : status;
        return (
          <div className={`tk-timeline-entry tk-dot-${statusClass(status)}`} key={entry.id}>
            <span className="tk-timeline-dot" />
            <div className="tk-timeline-content">
              <span className="tk-timeline-label">{label}</span>
              <small><Clock3 size={11} /> {formatDate(entry.created_at)}</small>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function Pagination({ page, lastPage, from, to, total, onPage, disabled }) {
  if (!lastPage) return null;
  const start = Math.max(1, Math.min(page - 2, lastPage - 4));
  const end = Math.min(lastPage, start + 4);
  const pages = Array.from({ length: end - start + 1 }, (_, index) => start + index);
  return (
    <footer className="tk-pagination">
      <span>Showing {from || 0} - {to || 0} of {total || 0} Tickets</span>
      <nav className="tk-page-buttons" aria-label="Ticket pages">
        <button type="button" disabled={disabled || page <= 1} onClick={() => onPage(page - 1)} aria-label="Previous page">
          <ArrowLeft size={15} />
        </button>
        {pages.map((number) => (
          <button
            type="button"
            key={number}
            className={number === page ? "tk-page-active" : ""}
            disabled={disabled}
            onClick={() => onPage(number)}
            aria-current={number === page ? "page" : undefined}
          >
            {number}
          </button>
        ))}
        <button type="button" disabled={disabled || page >= lastPage} onClick={() => onPage(page + 1)} aria-label="Next page">
          <ArrowLeft className="tk-arrow-next" size={15} />
        </button>
      </nav>
    </footer>
  );
}

export default function Tickets({ inactive = false }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [tickets, setTickets] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState(location.state?.notice || "");
  const requestId = useRef(0);
  const debouncedSearch = useDebouncedValue(search, 350).trim();

  const loadTickets = useCallback(async () => {
    const currentRequest = ++requestId.current;
    setLoading(true);
    setError("");
    try {
      const response = await listTickets({ search: debouncedSearch, page, inactive });
      if (currentRequest !== requestId.current) return;
      const result = response?.data;
      setTickets(Array.isArray(result?.data) ? result.data : []);
      setPagination(result || null);
    } catch (loadError) {
      if (currentRequest === requestId.current) {
        setError(loadError.message || "Unable to load tickets.");
        setTickets([]);
        setPagination(null);
      }
    } finally {
      if (currentRequest === requestId.current) setLoading(false);
    }
  }, [debouncedSearch, inactive, page]);

  useEffect(() => {
    const timer = window.setTimeout(loadTickets, 0);
    return () => window.clearTimeout(timer);
  }, [loadTickets]);

  useEffect(() => {
    if (location.state?.notice) {
      navigate(location.pathname, { replace: true, state: null });
    }
  }, [location.pathname, location.state, navigate]);

  const handleStatusChange = async (ticket) => {
    const action = inactive ? "Activate" : "Deactivate";
    if (!window.confirm(`${action} this ticket?`)) return;
    setBusyId(ticket.id);
    setError("");
    setNotice("");
    try {
      const response = inactive
        ? await activateTicket(ticket.id)
        : await deactivateTicket(ticket.id);
      setNotice(response?.message || `Ticket ${inactive ? "activated" : "deactivated"} successfully.`);
      await loadTickets();
    } catch (actionError) {
      setError(actionError.message || `Unable to ${action.toLowerCase()} this ticket.`);
    } finally {
      setBusyId(null);
    }
  };

  const title = inactive ? "Inactive Tickets" : "Ticket Management";

  return (
    <AdminLayout currentPath="/admin/software-tickets">
      <div className={`tk-page ${inactive ? "tk-page-inactive" : ""}`}>
        <header className="tk-header-card">
          <h1>
            {inactive ? <Archive size={27} /> : <Ticket size={30} />}
            {title}
          </h1>
          <div className="tk-header-actions">
            {inactive ? (
              <button type="button" className="tk-pill-button tk-back-button" onClick={() => navigate("/admin/software-tickets")}>
                <ArrowLeft size={16} /> Back to Tickets
              </button>
            ) : (
              <>
                <button type="button" className="tk-pill-button tk-inactive-button" onClick={() => navigate("/admin/software-tickets/inactive")}>
                  <Archive size={16} /> Inactive Tickets
                </button>
                <button type="button" className="tk-pill-button tk-raise-button" onClick={() => navigate("/admin/software-tickets/create")}>
                  <PlusCircle size={16} /> Raise Ticket
                </button>
              </>
            )}
          </div>
        </header>

        <section className="tk-list-card">
          <div className="tk-list-toolbar">
            <div className="tk-list-title">
              <h2><Ticket size={19} /> Ticket List</h2>
              <span>Total Tickets : <strong>{pagination?.total ?? 0}</strong></span>
            </div>
            <label className="tk-search">
              <Search size={18} />
              <input
                type="search"
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value);
                  setPage(1);
                }}
                placeholder="Search Ticket No, Category, Status..."
                aria-label="Search tickets"
              />
            </label>
          </div>

          {error ? <div className="tk-alert tk-alert-error" role="alert">{error}</div> : null}
          {notice ? <div className="tk-alert tk-alert-success" role="status">{notice}</div> : null}

          <div className="tk-table-scroll">
            <table className="tk-table">
              <thead>
                <tr>
                  <th>Sr.No</th>
                  <th>Ticket No</th>
                  <th>Module</th>
                  <th>Subject</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th className="tk-actions-heading">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td className="tk-state-row" colSpan="7">Loading tickets…</td></tr>
                ) : null}
                {!loading && !tickets.length ? (
                  <tr><td className="tk-state-row" colSpan="7">No tickets found.</td></tr>
                ) : null}
                {!loading ? tickets.map((ticket, index) => {
                  const ticketStatus = ticket.ticket_status || "Open";
                  const canEdit = !inactive && ticketStatus === "Open";
                  const currentPage = pagination?.current_page || page;
                  return (
                    <tr key={ticket.id}>
                      <td className="tk-row-number">{((currentPage - 1) * 10) + index + 1}</td>
                      <td className="tk-ticket-number">{ticket.ticket_no}</td>
                      <td><span className="tk-module-badge">{ticket.module?.module_name || "—"}</span></td>
                      <td className="tk-subject">{ticket.subject}</td>
                      <td><span className="tk-category-badge">{ticket.category}</span></td>
                      <td>
                        <div className="tk-status-cell">
                          <span className={`tk-status-badge tk-status-${statusClass(ticketStatus)}`}>
                            {ticketStatus === "Resolved" ? <CheckCircle2 size={13} /> : null}
                            {ticketStatus}
                          </span>
                          <StatusTimeline history={ticket.status_history || []} />
                        </div>
                      </td>
                      <td>
                        <div className="tk-row-actions">
                          <button type="button" className="tk-icon-button tk-view-action" aria-label="View ticket" title="View ticket" onClick={() => navigate(`/admin/software-tickets/${ticket.id}`, { state: { from: location.pathname } })}>
                            <Eye size={16} />
                          </button>
                          <button type="button" className="tk-icon-button tk-edit-action" aria-label="Edit ticket" title={canEdit ? "Edit ticket" : "Only Open tickets can be edited"} disabled={!canEdit} onClick={() => navigate(`/admin/software-tickets/${ticket.id}/edit`)}>
                            <Pencil size={15} />
                          </button>
                          <button
                            type="button"
                            className={`tk-icon-button ${inactive ? "tk-restore-action" : "tk-deactivate-action"}`}
                            aria-label={inactive ? "Activate ticket" : "Deactivate ticket"}
                            title={inactive ? "Activate ticket" : "Deactivate ticket"}
                            disabled={busyId === ticket.id}
                            onClick={() => handleStatusChange(ticket)}
                          >
                            {inactive ? <RotateCcw size={16} /> : <Trash2 size={16} />}
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                }) : null}
              </tbody>
            </table>
          </div>

          <Pagination
            page={pagination?.current_page || page}
            lastPage={pagination?.last_page || 1}
            from={pagination?.from}
            to={pagination?.to}
            total={pagination?.total}
            disabled={loading}
            onPage={setPage}
          />
        </section>
      </div>
    </AdminLayout>
  );
}
