import { useEffect, useRef, useState } from "react";
import { Eye, EyeOff, AlertCircle, Loader2 } from "lucide-react";
import { FaLinkedinIn, FaFacebookF, FaInstagram } from "react-icons/fa";
import {
  loginUser,
  registerUser,
  saveSession,
  getSession,
  homeForRole,
} from "../auth";
import "./Login.css";

/* ---------- Validation rules ---------- */
// Admin usernames: start with a letter, then letters / numbers / . _ -  (3 to 30 characters)
const USERNAME_RE = /^[A-Za-z][A-Za-z0-9._-]{2,29}$/;

const validate = ({ username, password, name, email }, registering = false) => {
  const errors = {};

  if (registering) {
    if (!name.trim()) errors.name = "Name is required.";
    if (!email.trim()) errors.email = "Email is required.";
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
      errors.email = "Enter a valid email address.";
    if (!/^[6-9]\d{9}$/.test(username))
      errors.username = "Enter a valid 10-digit mobile number.";
  } else if (!username) {
    errors.username = "Mobile number or username is required.";
  } else if (/^\d+$/.test(username)) {
    if (username.length !== 10) errors.username = "Enter a valid 10-digit mobile number.";
    else if (!/^[6-9]/.test(username)) errors.username = "Mobile number must start with 6, 7, 8 or 9.";
  } else if (!USERNAME_RE.test(username)) {
    errors.username =
      "Username must start with a letter (3-30 characters) and use only letters, numbers, dot, dash or underscore.";
  }

  if (!password) errors.password = "Password is required.";
  else if (password.length < 6) errors.password = "Password must be at least 6 characters.";

  return errors;
};

/* Remove spaces; also turns a pasted "+91 98765 43210" into "9876543210" */
const cleanUsername = (value) => {
  const v = value.replace(/\s/g, "");
  const match = v.match(/^\+?91(\d{10})$/);
  return (match ? match[1] : v).slice(0, 30);
};

const LIVE_LOGO = "https://aaryansmanomilan.com/website/Mano_Milan_Final_Website.png";

export default function Login() {
  const [values, setValues] = useState({
    username: localStorage.getItem("rememberedUser") || "",
    password: "",
    name: "",
    email: "",
  });
  const [isRegistering, setIsRegistering] = useState(() =>
    window.location.pathname.startsWith("/register"),
  );
  const [touched, setTouched] = useState({});
  const [remember, setRemember] = useState(!!localStorage.getItem("rememberedUser"));
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
    setTouched({ username: true, password: true, name: true, email: true });

    const currentErrors = validate(values, isRegistering);
    if (Object.keys(currentErrors).length > 0) {
      const firstInvalid = ["name", "email", "username", "password"].find(
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
          })
        : await loginUser(values.username, values.password);

      saveSession({ token, role, user });
      if (!isRegistering && remember)
        localStorage.setItem("rememberedUser", values.username);
      else localStorage.removeItem("rememberedUser");

      const mustChangePassword = !isRegistering && user?.is_password_changed === false;
      const passwordChangePath = role === "admin"
        ? "/admin/change-password"
        : "/candidate/change-password";
      window.location.assign(mustChangePassword ? passwordChangePath : homeForRole(role));
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
      <a className="login-logo" href="/">
        <img
          src="/stories/Mano_Milan_Final_Website.png"
          alt="Aaryans Manomilan"
          onError={(e) => {
            e.currentTarget.onerror = null;   // if the local file is missing, use the live site logo
            e.currentTarget.src = LIVE_LOGO;
          }}
        />
      </a>

      <div className="login-main">
        <form onSubmit={handleSubmit} noValidate>
          <h2>{isRegistering ? "Create your account" : "Sign in to account"}</h2>
          <p className="login-sub">
            {isRegistering
              ? "Enter your details to register"
              : "Enter your username &amp; password to login"}
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
                <label htmlFor="name">Full name</label>
                <div className="login-control">
                  <input
                    id="name"
                    name="name"
                    type="text"
                    autoComplete="name"
                    value={values.name}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    aria-invalid={!!showError("name")}
                  />
                </div>
                {showError("name") && (
                  <small className="login-error" role="alert">{errors.name}</small>
                )}
              </div>
              <div className="login-field">
                <label htmlFor="email">Email</label>
                <div className="login-control">
                  <input
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="email"
                    value={values.email}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    aria-invalid={!!showError("email")}
                  />
                </div>
                {showError("email") && (
                  <small className="login-error" role="alert">{errors.email}</small>
                )}
              </div>
            </>
          )}

          <div className="login-field">
            <label htmlFor="username">{isRegistering ? "Mobile number" : "Username"}</label>
            <div className={`login-control ${showError("username") ? "invalid" : ""}`}>
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
                maxLength={30}
                placeholder={isRegistering ? "10-digit mobile number" : "Mobile Number / Username"}
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
            <label htmlFor="password">Password</label>
            <div className={`login-control has-eye ${showError("password") ? "invalid" : ""}`}>
              <input
                ref={passRef}
                id="password"
                name="password"
                type={showPassword ? "text" : "password"}
                autoComplete="current-password"
                placeholder="*********"
                value={values.password}
                onChange={handleChange}
                onBlur={(e) => { handleBlur(e); setCapsOn(false); }}
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
                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
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
            <>
              <label className="login-check">
                <input
                  type="checkbox"
                  checked={remember}
                  onChange={(e) => setRemember(e.target.checked)}
                />
                <span>Remember password</span>
              </label>
              <a href="/forgot-password" className="login-forgot">Forgot password?</a>
            </>
          )}

          <button type="submit" className="login-btn" disabled={loading}>
            {loading ? (
              <>
                <Loader2 size={16} className="login-spin" /> {isRegistering ? "Creating account..." : "Signing in..."}
              </>
            ) : (
              isRegistering ? "Create account" : "Sign in"
            )}
          </button>

          {!isRegistering && (
          <>
          <div className="login-divider">Or Sign in with</div>

          <ul className="login-social">
            <li>
              <a
                href="https://www.linkedin.com/in/aaryans-manomilan-257615388/"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="LinkedIn"
              >
                <FaLinkedinIn />
              </a>
            </li>
            <li>
              <a
                href="https://www.facebook.com/share/19sczFNKnf/"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Facebook"
              >
                <FaFacebookF />
              </a>
            </li>
            <li>
              <a
                href="https://www.instagram.com/aaryansmanomilan?igsh=Y3p0cGd4b3I3a284"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Instagram"
              >
                <FaInstagram />
              </a>
            </li>
          </ul>
          </>
          )}

          <p className="login-foot">
            {isRegistering ? "Already have an account?" : "Don't have account?"}
            <a
              href="/login"
              onClick={(event) => {
                event.preventDefault();
                setIsRegistering(!isRegistering);
                setTouched({});
                setServerError("");
              }}
            >
              {isRegistering ? "Sign in" : "Create Account"}
            </a>
          </p>
        </form>
      </div>
    </div>
  );
}