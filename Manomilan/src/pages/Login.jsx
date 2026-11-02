import { useEffect, useRef, useState } from "react";
import {
  Eye,
  EyeOff,
  AlertCircle,
  Loader2,
  User,
  Lock,
  Users,
  LogIn,
  ArrowRight,
} from "lucide-react";
import {
  loginUser,
  registerUser,
  saveSession,
  getSession,
  homeForRole,
} from "../auth";
import DowntimeNotice from "../website/DowntimeNotice.jsx";
import "./Login.css";

/* ---------- Validation rules ---------- */
// Usernames: start with a letter, then letters / numbers / . _ -  (3 to 30 characters)
const USERNAME_RE = /^[A-Za-z][A-Za-z0-9._-]{2,29}$/;

const validate = ({ username, password, name, email, birth_date, gender }, registering = false) => {
  const errors = {};

  if (registering) {
    if (!name.trim()) errors.name = "Name is required.";
    if (!email.trim()) errors.email = "Email is required.";
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
      errors.email = "Enter a valid email address.";
    const now = new Date();
    const today = [
      now.getFullYear(),
      String(now.getMonth() + 1).padStart(2, "0"),
      String(now.getDate()).padStart(2, "0"),
    ].join("-");
    if (!birth_date) errors.birth_date = "Date of birth is required.";
    else if (birth_date >= today)
      errors.birth_date = "Date of birth must be before today.";
    if (!gender) errors.gender = "Profile type is required.";
    if (!/^[6-9]\d{9}$/.test(username))
      errors.username = "Enter a valid 10-digit mobile number.";
  } else if (!username) {
    errors.username = "Mobile number or username is required.";
  } else if (/^\d+$/.test(username)) {
    if (username.length !== 10)
      errors.username = "Enter a valid 10-digit mobile number.";
    else if (!/^[6-9]/.test(username))
      errors.username = "Mobile number must start with 6, 7, 8 or 9.";
  } else if (!USERNAME_RE.test(username)) {
    errors.username =
      "Username must start with a letter (3-30 characters) and use only letters, numbers, dot, dash or underscore.";
  }

  if (!password) errors.password = "Password is required.";
  else if (registering && password.length < 6)
    errors.password = "Password must be at least 6 characters.";

  return errors;
};

/* Remove spaces; also turns a pasted "+91 98765 43210" into "9876543210" */
const cleanUsername = (value) => {
  const v = value.replace(/\s/g, "");
  const match = v.match(/^\+?91(\d{10,})$/);

  if (/^\d+$/.test(v)) return v.slice(0, 10);
  if (match) return match[1].slice(0, 10);
  return v.slice(0, 30);
};

const LIVE_LOGO =
  "https://aaryansmanomilan.com/website/Mano_Milan_Final_Website.png";

export default function Login() {
  const [values, setValues] = useState({
    username: localStorage.getItem("rememberedUser") || "",
    password: "",
    name: "",
    email: "",
    birth_date: "",
    gender: "",
  });
  const [isRegistering, setIsRegistering] = useState(() =>
    window.location.pathname.startsWith("/register"),
  );
  const [touched, setTouched] = useState({});
  const [remember, setRemember] = useState(
    !!localStorage.getItem("rememberedUser"),
  );
  const [showPassword, setShowPassword] = useState(false);
  const [capsOn, setCapsOn] = useState(false);
  const [loading, setLoading] = useState(false);
  const [serverError, setServerError] = useState("");

  const userRef = useRef(null);
  const passRef = useRef(null);

  // If already signed in, skip the login screen
  useEffect(() => {
    const session = getSession();
    if (session) window.location.replace(homeForRole(session.role));
  }, []);

  const errors = validate(values, isRegistering);
  const showError = (field) => touched[field] && errors[field];

  const handleChange = (e) => {
    const { name, value } = e.target;
    setServerError("");
    setValues((v) => ({
      ...v,
      [name]: name === "username" ? cleanUsername(value) : value,
    }));
  };

  const handleBlur = (e) => {
    setTouched((t) => ({ ...t, [e.target.name]: true }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setServerError("");
    setTouched({
      username: true,
      password: true,
      name: true,
      email: true,
      birth_date: true,
      gender: true,
    });

    const currentErrors = validate(values, isRegistering);
    if (Object.keys(currentErrors).length > 0) {
      const firstInvalid = ["name", "email", "birth_date", "gender", "username", "password"].find(
        (field) => currentErrors[field],
      );
      e.currentTarget.elements.namedItem(firstInvalid)?.focus();
      return;
    }

    setLoading(true);
    try {
      const { token, role, user } = isRegistering
        ? await registerUser({
            name: values.name.trim(),
            email: values.email.trim(),
            phone: values.username,
            password: values.password,
            birth_date: values.birth_date,
            gender: values.gender,
          })
        : await loginUser(values.username, values.password);

      saveSession({ token, role, user });
      if (!isRegistering && remember)
        localStorage.setItem("rememberedUser", values.username);
      else localStorage.removeItem("rememberedUser");

      const mustChangePassword =
        !isRegistering && user?.is_password_changed === false;
      const passwordChangePath =
        role === "admin"
          ? "/admin/change-password"
          : "/candidate/change-password";
      const nextPath = isRegistering
        ? "/candidate/profile"
        : mustChangePassword
          ? passwordChangePath
          : homeForRole(role);
      window.location.assign(nextPath);
    } catch (err) {
      setServerError(err.message || "Login failed. Please try again.");
      setValues((v) => ({ ...v, password: "" }));
      passRef.current?.focus();
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-page">
      {/* ---------- Left hero panel ---------- */}
      <section className="login-hero">
        <a className="login-logo" href="/">
          <img
            src="/stories/Mano_Milan_Final_Website.png"
            alt="Aaryans Manomilan"
            onError={(e) => {
              e.currentTarget.onerror = null; // local file missing -> use live logo
              e.currentTarget.src = LIVE_LOGO;
            }}
          />
        </a>
        <h1 className="login-welcome">
          Welcome <em>Back!</em>
        </h1>
        <p className="login-welcome-sub">
          Login to continue your
          <br />
          <strong>Melava</strong> journey...
        </p>
      </section>

      {/* ---------- Right card ---------- */}
      <div className="login-main">
        <DowntimeNotice placement="login" />
        <form className="login-card" onSubmit={handleSubmit} noValidate>
          <div className="login-badge" aria-hidden="true">
            <Users size={34} />
          </div>

          <h2>{isRegistering ? "Create your account" : "Sign in to account"}</h2>
          <p className="login-sub">
            {isRegistering
              ? "Enter your details to register"
              : "Enter your username & password to login"}
          </p>

          {serverError && (
            <div className="login-alert" role="alert">
              <AlertCircle size={18} />
              <span>{serverError}</span>
            </div>
          )}

          {isRegistering && (
            <>
              <div className="login-field">
                <label htmlFor="name">
                  <User size={16} /> Full name
                </label>
                <div className={`login-control ${showError("name") ? "invalid" : ""}`}>
                  <User size={18} className="login-ico" />
                  <input
                    id="name"
                    name="name"
                    type="text"
                    autoComplete="name"
                    placeholder="Enter your full name"
                    value={values.name}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    aria-invalid={!!showError("name")}
                  />
                </div>
                {showError("name") && (
                  <small className="login-error" role="alert">
                    {errors.name}
                  </small>
                )}
              </div>

              <div className="login-field">
                <label htmlFor="email">
                  <User size={16} /> Email
                </label>
                <div className={`login-control ${showError("email") ? "invalid" : ""}`}>
                  <User size={18} className="login-ico" />
                  <input
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="email"
                    placeholder="Enter your email"
                    value={values.email}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    aria-invalid={!!showError("email")}
                  />
                </div>
                {showError("email") && (
                  <small className="login-error" role="alert">
                    {errors.email}
                  </small>
                )}
              </div>

              <div className="login-field">
                <label htmlFor="birth_date">
                  <User size={16} /> Date of birth
                </label>
                <div className={`login-control ${showError("birth_date") ? "invalid" : ""}`}>
                  <User size={18} className="login-ico" />
                  <input
                    id="birth_date"
                    name="birth_date"
                    type="date"
                    autoComplete="bday"
                    value={values.birth_date}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    aria-invalid={!!showError("birth_date")}
                  />
                </div>
                {showError("birth_date") && (
                  <small className="login-error" role="alert">
                    {errors.birth_date}
                  </small>
                )}
              </div>

              <div className="login-field">
                <label htmlFor="gender">
                  <User size={16} /> Profile type
                </label>
                <div className={`login-control ${showError("gender") ? "invalid" : ""}`}>
                  <User size={18} className="login-ico" />
                  <select
                    id="gender"
                    name="gender"
                    value={values.gender}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    aria-invalid={!!showError("gender")}
                  >
                    <option value="">Select profile type</option>
                    <option value="groom">Groom</option>
                    <option value="bride">Bride</option>
                  </select>
                </div>
                {showError("gender") && (
                  <small className="login-error" role="alert">
                    {errors.gender}
                  </small>
                )}
              </div>
            </>
          )}

          {/* Username */}
          <div className="login-field">
            <label htmlFor="username">
              <User size={16} /> {isRegistering ? "Mobile number" : "Username"}
            </label>
            <div className={`login-control ${showError("username") ? "invalid" : ""}`}>
              <User size={18} className="login-ico" />
              <input
                ref={userRef}
                id="username"
                name="username"
                type="text"
                inputMode={isRegistering ? "numeric" : "text"}
                autoComplete={isRegistering ? "tel" : "username"}
                autoCapitalize="none"
                autoCorrect="off"
                spellCheck={false}
                maxLength={isRegistering ? 10 : 30}
                placeholder={
                  isRegistering ? "Enter 10-digit mobile number" : "Enter your username"
                }
                value={values.username}
                onChange={handleChange}
                onBlur={handleBlur}
                aria-invalid={!!showError("username")}
                aria-describedby="username-error"
              />
            </div>
            {showError("username") && (
              <small id="username-error" className="login-error" role="alert">
                {errors.username}
              </small>
            )}
          </div>

          {/* Password */}
          <div className="login-field">
            <label htmlFor="password">
              <Lock size={16} /> Password
            </label>
            <div
              className={`login-control has-eye ${showError("password") ? "invalid" : ""}`}
            >
              <Lock size={18} className="login-ico" />
              <input
                ref={passRef}
                id="password"
                name="password"
                type={showPassword ? "text" : "password"}
                autoComplete={isRegistering ? "new-password" : "current-password"}
                placeholder="Enter your password"
                value={values.password}
                onChange={handleChange}
                onBlur={(e) => {
                  handleBlur(e);
                  setCapsOn(false);
                }}
                onKeyUp={(e) => setCapsOn(e.getModifierState("CapsLock"))}
                aria-invalid={!!showError("password")}
                aria-describedby="password-error"
              />
              <button
                type="button"
                className="login-eye"
                onClick={() => setShowPassword(!showPassword)}
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                {showPassword ? <Eye size={20} /> : <EyeOff size={20} />}
              </button>
            </div>
            {showError("password") && (
              <small id="password-error" className="login-error" role="alert">
                {errors.password}
              </small>
            )}
            {capsOn && <small className="login-warn">Caps Lock is on</small>}
          </div>

          {/* Remember + Forgot */}
          {!isRegistering && (
            <div className="login-row">
              <label className="login-check">
                <input
                  type="checkbox"
                  checked={remember}
                  onChange={(e) => setRemember(e.target.checked)}
                />
                <span>Remember password</span>
              </label>
              <a href="/forgot-password" className="login-forgot">
                Forgot password?
              </a>
            </div>
          )}

          <button type="submit" className="login-btn" disabled={loading}>
            {loading ? (
              <>
                <Loader2 size={18} className="login-spin" />
                {isRegistering ? "Creating account..." : "Signing in..."}
              </>
            ) : (
              <>
                <LogIn size={18} />
                {isRegistering ? "Create account" : "Sign in"}
              </>
            )}
          </button>

          <p className="login-foot">
            {isRegistering ? "Already have an account?" : "Don't have account?"}
            <a
              href={isRegistering ? "/login" : "/register"}
              onClick={(event) => {
                event.preventDefault();
                setIsRegistering(!isRegistering);
                setTouched({});
                setServerError("");
              }}
            >
              {isRegistering ? "Sign in" : "Create Account"}
              <ArrowRight size={16} />
            </a>
          </p>
        </form>
      </div>
    </div>
  );
}