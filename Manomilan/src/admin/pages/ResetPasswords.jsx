import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Check, Copy, KeyRound, Search, UserCheck, Users, UserX, X } from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import AdminStats from "../components/AdminStats";
import AdminPagination from "../components/AdminPagination";
import useDebouncedValue from "../hooks/useDebouncedValue";
import { request } from "../../api";
import "../components/admin-ui.css";
import "./resetPasswords.css";

const loadUsers = ({ query, gender, status, page }) => {
  const params = new URLSearchParams({ per_page: "20", page: String(page) });
  if (query) params.set("q", query);
  if (gender !== "all") params.set("gender", gender);
  if (status !== "all") params.set("status", status);
  return request(`/admin/users?${params}`);
};

const resetPassword = (id) =>
  request(`/admin/users/${id}/reset-password`, { method: "POST" });

export default function ResetPasswords() {
  const [search, setSearch] = useState("");
  const [gender, setGender] = useState("all");
  const [status, setStatus] = useState("all");
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

  const debouncedSearch = useDebouncedValue(search, 350);
  const activeQuery = debouncedSearch.trim();

  const load = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    setError("");
    try {
      const response = await loadUsers({ query: activeQuery, gender, status, page });
      if (id !== requestId.current) return;
      setUsers(Array.isArray(response?.data?.data) ? response.data.data : []);
      setPagination(response?.data || null);
      setStats(response?.stats || { total: 0, active: 0, inactive: 0 });
    } catch (requestError) {
      if (id === requestId.current) setError(requestError.message || "Unable to load accounts.");
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [activeQuery, gender, page, status]);

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

  const statItems = useMemo(() => [
    { key: "total", label: "Total Accounts", value: stats?.total ?? 0, icon: Users, tone: "teal", hint: "Matching search" },
    { key: "active", label: "Active Accounts", value: stats?.active ?? 0, icon: UserCheck, tone: "green", hint: "Can sign in" },
    { key: "inactive", label: "Inactive Accounts", value: stats?.inactive ?? 0, icon: UserX, tone: "amber", hint: "Access disabled" },
  ], [stats]);

  return (
    <AdminLayout currentPath="/admin/reset-passwords">
      <div className="adm-ui rp-page">
        <div className="ui-head">
          <div>
            <h1>Reset Passwords</h1>
            <p>Reset an account to the default password. The user must change it after signing in.</p>
          </div>
        </div>

        <div className="ui-alert success">
          Reset accounts receive the default password and are required to choose a new password before continuing.
        </div>

        <AdminStats items={statItems} loading={loading && !stats} label="User account statistics" />

        {error ? <div className="ui-alert" role="alert">{error}</div> : null}
        {notice ? <div className="ui-alert success" role="status">{notice}</div> : null}

        <div className="ui-card ui-toolbar">
          <label className="ui-search">
            <Search size={17} />
            <input
              type="search"
              value={search}
              onChange={(event) => { setSearch(event.target.value); setPage(1); }}
              placeholder="Search name, username, email, or profile number"
              aria-label="Search accounts"
            />
            {search ? (
              <button type="button" onClick={() => { setSearch(""); setPage(1); }} aria-label="Clear search"><X size={16} /></button>
            ) : null}
          </label>
          <select
            className="ui-select"
            value={gender}
            onChange={(event) => { setGender(event.target.value); setPage(1); }}
            aria-label="Filter by groom or bride"
          >
            <option value="all">All Profiles</option>
            <option value="groom">Groom</option>
            <option value="bride">Bride</option>
          </select>
          <select
            className="ui-select"
            value={status}
            onChange={(event) => { setStatus(event.target.value); setPage(1); }}
            aria-label="Filter by account status"
          >
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div className="ui-card ui-table-card">
          <div className={`ui-table-scroll ${loading ? "is-loading" : ""}`}>
            <table className="ui-table">
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
                {loading && !users.length ? Array.from({ length: 6 }, (_, index) => (
                  <tr className="ui-skeleton-row" key={`skeleton-${index}`}>
                    <td colSpan="6"><span /></td>
                  </tr>
                )) : null}

                {!loading && !users.length ? (
                  <tr>
                    <td colSpan="6">
                      <div className="ui-state">
                        <Search size={30} />
                        <strong>No accounts found</strong>
                        <p>No accounts match your current search.</p>
                      </div>
                    </td>
                  </tr>
                ) : null}

                {users.map((user) => (
                  <tr key={user.id}>
                    <td>
                      <div className="ui-person-text">
                        <strong>{user.name || user.profile?.full_name || "Unnamed account"}</strong>
                        <small>{user.email || "No email address"}</small>
                      </div>
                    </td>
                    <td>
                      <span className="ui-person-text">
                        <strong>{user.profile?.full_name || "No linked profile"}</strong>
                        {user.profile?.registration_number || user.profile?.serial_no
                          ? <small>{user.profile.registration_number || user.profile.serial_no}</small>
                          : null}
                      </span>
                    </td>
                    <td className="ui-muted">{user.username || "—"}</td>
                    <td>
                      <span className={`ui-badge ${["active", "inactive"].includes(String(user.status || "").toLowerCase()) ? (String(user.status).toLowerCase() === "active" ? "ok" : "off") : "neutral"}`}>
                        {user.status || "Unknown"}
                      </span>
                    </td>
                    <td className="ui-muted">{user.created_at ? new Date(user.created_at).toLocaleDateString("en-IN") : "—"}</td>
                    <td>
                      <button type="button" className="ui-btn small" onClick={() => setSelected(user)} disabled={busy}>
                        <KeyRound size={15} /> Reset
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pagination ? (
            <AdminPagination
              page={pagination.current_page || page}
              lastPage={pagination.last_page || 1}
              from={pagination.from}
              to={pagination.to}
              total={pagination.total}
              onPage={setPage}
              disabled={loading}
              label="Account pagination"
            />
          ) : null}
        </div>

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
