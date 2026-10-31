import { useCallback, useEffect, useRef, useState } from "react";
import { Check, ChevronLeft, ChevronRight, Copy, KeyRound, Search } from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { request } from "../../api";
import "./resetPasswords.css";

const loadUsers = ({ query, page }) => {
  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (query) params.set("q", query);
  return request(`/admin/users?${params}`);
};

const resetPassword = (id) =>
  request(`/admin/users/${id}/reset-password`, { method: "POST" });

export default function ResetPasswords() {
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [users, setUsers] = useState([]);
  const [stats, setStats] = useState(null);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [selected, setSelected] = useState(null);
  const [credential, setCredential] = useState(null);
  const [copied, setCopied] = useState(false);
  const [notice, setNotice] = useState("");
  const requestId = useRef(0);

  const load = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    setError("");
    try {
      const response = await loadUsers({ query, page });
      if (id !== requestId.current) return;
      setUsers(Array.isArray(response?.data?.data) ? response.data.data : []);
      setPagination(response?.data || null);
      setStats(response?.stats || { total: 0, active: 0, inactive: 0 });
    } catch (requestError) {
      if (id === requestId.current) setError(requestError.message || "Unable to load accounts.");
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [query, page]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setQuery(search.trim());
      setPage(1);
    }, 350);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const handleReset = async () => {
    if (!selected || busy) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const response = await resetPassword(selected.id);
      setCredential({
        ...selected,
        password: response?.data?.default_password,
      });
      setSelected(null);
      if (!response?.data?.default_password) {
        setError("The password was reset, but the default password was not returned. Contact support before sharing credentials.");
      } else {
        setNotice(response.message || "The default password was assigned. The account holder must change it after signing in.");
      }
      await load();
    } catch (requestError) {
      setError(requestError.message || "Unable to reset this password.");
    } finally {
      setBusy(false);
    }
  };

  const copyPassword = async () => {
    try {
      await navigator.clipboard.writeText(credential.password);
      setCopied(true);
    } catch {
      setError("Could not copy to clipboard. Select and copy the temporary password manually.");
    }
  };

  const closeCredential = () => {
    setCredential(null);
    setCopied(false);
  };

  return (
    <AdminLayout currentPath="/admin/reset-passwords">
      <div className="rp-page">
        <header className="rp-heading">
          <div>
            <h1>Reset Passwords</h1>
            <p>Reset an account to the default password. The user must change it after signing in.</p>
          </div>
        </header>

        <div className="rp-notice">
          Reset accounts receive the default password and are required to choose a new password before continuing.
        </div>
        {error ? <div className="rp-error" role="alert">{error}</div> : null}
        {notice ? <div className="rp-success" role="status">{notice}</div> : null}

        <section className="rp-stats" aria-label="User account statistics">
          <article className="rp-stat">
            <span>Total users</span>
            <strong>{stats ? stats.total.toLocaleString("en-IN") : loading ? "—" : "0"}</strong>
          </article>
          <article className="rp-stat active">
            <span>Active users</span>
            <strong>{stats ? stats.active.toLocaleString("en-IN") : loading ? "—" : "0"}</strong>
          </article>
          <article className="rp-stat inactive">
            <span>Inactive users</span>
            <strong>{stats ? stats.inactive.toLocaleString("en-IN") : loading ? "—" : "0"}</strong>
          </article>
        </section>

        <section className="rp-card">
          <label className="rp-search">
            <Search size={17} />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search name, username, email, or profile number"
              aria-label="Search accounts"
            />
          </label>

          <div className="rp-table-wrap">
            <table className="rp-table">
              <thead>
                <tr>
                  <th>Account</th>
                  <th>Profile</th>
                  <th>Username</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan="6" className="rp-state">Loading accounts…</td></tr>
                ) : null}
                {!loading && users.map((user) => (
                  <tr key={user.id}>
                    <td>
                      <strong>{user.name || user.profile?.full_name || "Unnamed account"}</strong>
                      <small>{user.email || "No email address"}</small>
                    </td>
                    <td>
                      {user.profile?.full_name || "No linked profile"}
                      {user.profile?.registration_number || user.profile?.serial_no
                        ? <small>{user.profile.registration_number || user.profile.serial_no}</small>
                        : null}
                    </td>
                    <td>{user.username || "—"}</td>
                    <td>
                      <span className={`rp-status ${["active", "inactive"].includes(String(user.status || "").toLowerCase()) ? String(user.status).toLowerCase() : "unknown"}`}>
                        {user.status || "Unknown"}
                      </span>
                    </td>
                    <td>{user.created_at ? new Date(user.created_at).toLocaleDateString("en-IN") : "—"}</td>
                    <td>
                      <button type="button" className="rp-reset-button" onClick={() => setSelected(user)} disabled={busy}>
                        <KeyRound size={15} /> Reset
                      </button>
                    </td>
                  </tr>
                ))}
                {!loading && !users.length ? (
                  <tr><td colSpan="6" className="rp-state">No accounts match your search.</td></tr>
                ) : null}
              </tbody>
            </table>
          </div>

          {pagination?.last_page > 1 ? (
            <nav className="rp-pagination" aria-label="Account pagination">
              <span>
                Showing {pagination.from ?? 0}–{pagination.to ?? 0} of {pagination.total.toLocaleString("en-IN")}
              </span>
              <div>
                <button type="button" onClick={() => setPage((value) => value - 1)} disabled={page <= 1 || loading} aria-label="Previous page">
                  <ChevronLeft size={17} />
                </button>
                <span>Page {page} of {pagination.last_page}</span>
                <button type="button" onClick={() => setPage((value) => value + 1)} disabled={page >= pagination.last_page || loading} aria-label="Next page">
                  <ChevronRight size={17} />
                </button>
              </div>
            </nav>
          ) : null}
        </section>

        {selected ? (
          <div className="rp-modal" onClick={() => !busy && setSelected(null)}>
            <section className="rp-dialog" role="alertdialog" aria-modal="true" aria-labelledby="rp-confirm-title" onClick={(event) => event.stopPropagation()}>
              <h2 id="rp-confirm-title">Set the default password?</h2>
              <p>This changes the sign-in password for <strong>{selected.name || selected.username}</strong>.</p>
              <div className="rp-dialog-actions">
                <button type="button" className="rp-secondary" onClick={() => setSelected(null)} disabled={busy}>Cancel</button>
                <button type="button" className="rp-reset-button" onClick={handleReset} disabled={busy}>
                  <KeyRound size={15} /> {busy ? "Resetting…" : "Set default password"}
                </button>
              </div>
            </section>
          </div>
        ) : null}

        {credential?.password ? (
          <div className="rp-modal" onClick={closeCredential}>
            <section className="rp-dialog" role="dialog" aria-modal="true" aria-labelledby="rp-password-title" onClick={(event) => event.stopPropagation()}>
              <h2 id="rp-password-title">Default password assigned</h2>
              <p>
                <strong>{credential.name || credential.username}</strong> must change this password after signing in.
              </p>
              <div className="rp-credential">
                <code>{credential.password}</code>
                <button type="button" className="rp-copy-button" onClick={copyPassword} aria-label="Copy temporary password">
                  {copied ? <Check size={17} /> : <Copy size={17} />}
                  {copied ? "Copied" : "Copy"}
                </button>
              </div>
              <div className="rp-dialog-actions">
                <button type="button" className="rp-reset-button" onClick={closeCredential}>Done</button>
              </div>
            </section>
          </div>
        ) : null}
      </div>
    </AdminLayout>
  );
}
