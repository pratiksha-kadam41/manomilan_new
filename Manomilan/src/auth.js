const BASE = (import.meta.env.VITE_API_URL || "http://localhost:8000/api").replace(/\/+$/, "");
const ADMIN_ROLES = ["admin", "super"];

const SESSION_KEY = "auth";
const IDLE_LIMIT = 15 * 60 * 1000;
const MAX_AGE = 8 * 60 * 60 * 1000;

localStorage.removeItem("authToken");
localStorage.removeItem("authRole");
localStorage.removeItem("adminToken");

export const isAdminRole = (role) => ADMIN_ROLES.includes(role);
const roleForUser = (user) =>
  Number(user?.role_id) === 1 || String(user?.user_type) === "00"
    ? "admin"
    : "candidate";

export const homeForRole = (role) =>
  isAdminRole(role) ? "#/admin" : "#/candidate";

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
  const session = read();
  if (!session) return null;

  const now = Date.now();
  if (
    now - session.lastActive > IDLE_LIMIT ||
    now - session.startedAt > MAX_AGE
  ) {
    clearSession();
    return null;
  }
  return { token: session.token, role: session.role };
}

export function touchSession() {
  const session = read();
  if (!session) return;
  session.lastActive = Date.now();
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
}

export function clearSession() {
  const session = read();
  sessionStorage.removeItem(SESSION_KEY);

  if (session?.token) {
    fetch(`${BASE}/logout`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${session.token}` },
    }).catch(() => {});
  }
}

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
  events.forEach((event) =>
    window.addEventListener(event, bump, { passive: true }),
  );

  const timer = setInterval(() => {
    if (!getSession()) onExpire();
  }, 15000);

  return () => {
    events.forEach((event) => window.removeEventListener(event, bump));
    clearInterval(timer);
  };
}

async function readError(res, fallback) {
  const body = await res.json().catch(() => ({}));
  const firstError = Object.values(body.errors || {})[0];
  if (Array.isArray(firstError)) return firstError[0];
  return body.message || fallback;
}

export async function loginUser(username, password) {
  let res;
  try {
    res = await fetch(`${BASE}/login`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ username, password }),
    });
  } catch {
    throw new Error("Cannot reach the server. Check your internet and try again.");
  }

  if (res.status === 401) throw new Error("Invalid username or password.");
  if (res.status === 422) {
    throw new Error(await readError(res, "Invalid username or password."));
  }
  if (res.status === 429) {
    throw new Error("Too many attempts. Please wait a few minutes and try again.");
  }
  if (res.status === 403) {
    throw new Error(await readError(res, "Your account cannot sign in."));
  }
  if (!res.ok) {
    throw new Error(await readError(res, "Please try again later."));
  }

  const body = await res.json();
  if (!body.token || !body.user) {
    throw new Error("The server returned an invalid login response.");
  }
  return { token: body.token, role: roleForUser(body.user), user: body.user };
}

export async function registerUser({ name, email, phone, password }) {
  let res;
  try {
    res = await fetch(`${BASE}/register`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ name, email, phone, password }),
    });
  } catch {
    throw new Error("Cannot reach the server. Check your internet and try again.");
  }

  if (!res.ok) {
    throw new Error(
      await readError(res, "Registration failed. Please try again later."),
    );
  }

  const body = await res.json();
  if (!body.token || !body.user) {
    throw new Error("The server returned an invalid registration response.");
  }
  return { token: body.token, role: roleForUser(body.user), user: body.user };
}

export async function getAdminDashboard() {
  const session = getSession();
  if (!session?.token) {
    throw new Error("Your session has expired. Please sign in again.");
  }

  let res;
  try {
    res = await fetch(`${BASE}/dashboard`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${session.token}`,
      },
    });
  } catch {
    throw new Error("Cannot reach the server. Check your internet and try again.");
  }

  if (res.status === 401) {
    clearSession();
    window.location.hash = "#/login?expired=1";
    throw new Error("Your session has expired. Please sign in again.");
  }

  const body = await res.json().catch(() => ({}));
  if (!res.ok || !body.success || !body.data) {
    throw new Error(body.message || "Could not load the admin dashboard.");
  }
  return body.data;
}
