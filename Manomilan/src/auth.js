const BASE = import.meta.env.VITE_API_URL;
const ADMIN_ROLES = ["admin", "super"];

const SESSION_KEY = "auth";
const IDLE_LIMIT = 15 * 60 * 1000; // sign out after 15 minutes of no activity
const MAX_AGE = 8 * 60 * 60 * 1000; // sign out after 8 hours no matter what

// One-time cleanup of old sessions saved by earlier versions of this app
localStorage.removeItem("authToken");
localStorage.removeItem("authRole");
localStorage.removeItem("adminToken");

export const isAdminRole = (role) => ADMIN_ROLES.includes(role);

// Where each type of person lands after login
export const homeForRole = (role) =>
  isAdminRole(role) ? "#/admin" : "#/candidate";

/* ---------- Session helpers ----------
   sessionStorage is wiped when the tab or browser is closed.
   When your backend uses httpOnly cookies, these functions will ask the
   server instead, and nothing will be stored in the browser. */
const read = () => {
  try {
    return JSON.parse(sessionStorage.getItem(SESSION_KEY));
  } catch {
    return null;
  }
};

export function saveSession({ token, role }) {
  const now = Date.now();
  sessionStorage.setItem(
    SESSION_KEY,
    JSON.stringify({ token, role, startedAt: now, lastActive: now }),
  );
}

export function getSession() {
  const s = read();
  if (!s) return null;

  const now = Date.now();
  if (now - s.lastActive > IDLE_LIMIT || now - s.startedAt > MAX_AGE) {
    clearSession(); // expired
    return null;
  }
  return { token: s.token, role: s.role };
}

// Records that the person is still active
export function touchSession() {
  const s = read();
  if (!s) return;
  s.lastActive = Date.now();
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(s));
}

// Logout: clears the browser copy and tells the server to cancel the session
export function clearSession() {
  const s = read();
  sessionStorage.removeItem(SESSION_KEY);

  if (BASE && s?.token) {
    fetch(`${BASE}/logout`, {
      method: "POST",
      headers: { Authorization: `Bearer ${s.token}` },
    }).catch(() => {}); // even if this fails, the person is logged out here
  }
}

// Watches for activity; calls onExpire when the session has timed out
export function watchActivity(onExpire) {
  let last = 0;
  const bump = () => {
    const now = Date.now();
    if (now - last > 30000) {
      last = now;
      touchSession();
    }
  };

  const events = ["click", "keydown", "mousemove", "scroll", "touchstart"];
  events.forEach((e) => window.addEventListener(e, bump, { passive: true }));

  const timer = setInterval(() => {
    if (!getSession()) onExpire();
  }, 15000);

  return () => {
    events.forEach((e) => window.removeEventListener(e, bump));
    clearInterval(timer);
  };
}

/* ---------- Login ---------- */
export async function loginUser(username, password) {
  // ---- TEMPORARY TEST LOGINS (only run when VITE_API_URL is not set) ----
  //   Admin:      admin / Admin@123
  //   Candidate:  8888888888 / Candidate@123
  // Delete this block once your real API is connected.
  if (!BASE) {
    await new Promise((r) => setTimeout(r, 800));
    if (import.meta.env.DEV) {
      if (username.toLowerCase() === "admin" && password === "Admin@123")
        return { token: "dev-admin", role: "admin" };
      if (username === "8888888888" && password === "Candidate@123")
        return { token: "dev-candidate", role: "candidate" };
    }
    throw new Error("Invalid username or password.");
  }
  // -----------------------------------------------------------------------

  let res;
  try {
    res = await fetch(`${BASE}/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ username, password }),
    });
  } catch {
    throw new Error(
      "Cannot reach the server. Check your internet and try again.",
    );
  }

  if (res.status === 401 || res.status === 422)
    throw new Error("Invalid username or password.");
  if (res.status === 429)
    throw new Error(
      "Too many attempts. Please wait a few minutes and try again.",
    );
  if (!res.ok)
    throw new Error(
      "Something went wrong on our side. Please try again later.",
    );

  return res.json(); // expected: { token: "...", role: "admin" | "super" | "candidate" }
}
