import { useState } from "react";
import { AlertTriangle, Eye, EyeOff, KeyRound, Loader2, ShieldCheck } from "lucide-react";
import AdminLayout from "../admin/components/AdminLayout";
import CandidateLayout from "../candidate/components/CandidateLayout";
import { request } from "../api";
import { getSession, homeForRole, isAdminRole, markPasswordChanged } from "../auth";
import "./change-password.css";

const EMPTY = { current_password: "", new_password: "", new_password_confirmation: "" };

export default function ChangePassword() {
  const session = getSession();
  const admin = isAdminRole(session?.role);
  const Layout = admin ? AdminLayout : CandidateLayout;
  const currentPath = admin ? "/admin/change-password" : "/candidate/change-password";

  const [values, setValues] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [visiblePasswords, setVisiblePasswords] = useState({});

  const togglePasswordVisibility = (field) => {
    setVisiblePasswords((current) => ({ ...current, [field]: !current[field] }));
  };

  const update = (field) => (event) => {
    setValues((prev) => ({ ...prev, [field]: event.target.value }));
    setSuccess("");
  };

  const submit = async (event) => {
    event.preventDefault();
    setError("");
    setSuccess("");

    if (values.new_password !== values.new_password_confirmation) {
      setError("New password and confirmation do not match.");
      return;
    }

    setSaving(true);
    try {
      const body = await request("/change-password", {
        method: "PUT",
        body: values,
      });
      setValues(EMPTY);
      setSuccess(body?.message || "Password changed successfully.");
      markPasswordChanged();
      window.setTimeout(() => window.location.replace(homeForRole(session?.role)), 700);
    } catch (err) {
      setError(err.message || "Could not change your password.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Layout currentPath={currentPath}>
      <div className="cp-page">
        <div className="cp-card">
          <div className="cp-head">
            <span className="cp-icon"><KeyRound size={22} /></span>
            <div>
              <h1>Change Password</h1>
              <p>
                {session?.user?.is_password_changed === false
                  ? "For your account security, create a new password before continuing."
                  : "Choose a strong password you do not use anywhere else."}
              </p>
            </div>
          </div>

          {error && (
            <div className="cp-alert error" role="alert">
              <AlertTriangle size={17} /> {error}
            </div>
          )}
          {success && (
            <div className="cp-alert success" role="status">
              <ShieldCheck size={17} /> {success}
            </div>
          )}

          <form onSubmit={submit} className="cp-form">
            {session?.user?.is_password_changed !== false ? (
              <label>
                Current password
                <span className="cp-password-control">
                  <input
                    type={visiblePasswords.current_password ? "text" : "password"}
                    value={values.current_password}
                    onChange={update("current_password")}
                    autoComplete="current-password"
                    required
                  />
                  <button
                    type="button"
                    className="cp-password-toggle"
                    onClick={() => togglePasswordVisibility("current_password")}
                    aria-label={`${visiblePasswords.current_password ? "Hide" : "Show"} current password`}
                    aria-pressed={Boolean(visiblePasswords.current_password)}
                  >
                    {visiblePasswords.current_password ? <EyeOff size={19} /> : <Eye size={19} />}
                  </button>
                </span>
              </label>
            ) : null}
            <label>
              New password
              <span className="cp-password-control">
                <input
                  type={visiblePasswords.new_password ? "text" : "password"}
                  value={values.new_password}
                  onChange={update("new_password")}
                  autoComplete="new-password"
                  minLength={6}
                  required
                />
                <button
                  type="button"
                  className="cp-password-toggle"
                  onClick={() => togglePasswordVisibility("new_password")}
                  aria-label={`${visiblePasswords.new_password ? "Hide" : "Show"} new password`}
                  aria-pressed={Boolean(visiblePasswords.new_password)}
                >
                  {visiblePasswords.new_password ? <EyeOff size={19} /> : <Eye size={19} />}
                </button>
              </span>
            </label>
            <label>
              Confirm new password
              <span className="cp-password-control">
                <input
                  type={visiblePasswords.new_password_confirmation ? "text" : "password"}
                  value={values.new_password_confirmation}
                  onChange={update("new_password_confirmation")}
                  autoComplete="new-password"
                  minLength={6}
                  required
                />
                <button
                  type="button"
                  className="cp-password-toggle"
                  onClick={() => togglePasswordVisibility("new_password_confirmation")}
                  aria-label={`${visiblePasswords.new_password_confirmation ? "Hide" : "Show"} password confirmation`}
                  aria-pressed={Boolean(visiblePasswords.new_password_confirmation)}
                >
                  {visiblePasswords.new_password_confirmation ? <EyeOff size={19} /> : <Eye size={19} />}
                </button>
              </span>
            </label>

            <button type="submit" className="cp-submit" disabled={saving}>
              {saving ? <><Loader2 size={17} className="cp-spin" /> Saving…</> : "Update Password"}
            </button>
          </form>
        </div>
      </div>
    </Layout>
  );
}
