import { request } from "./api";

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
  isAdminRole(role) ? "/admin" : "/candidate";

const read = () => {
  try {
    return JSON.parse(sessionStorage.getItem(SESSION_KEY));
  } catch {
    return null;
  }
};

export function saveSession({ token, role, user = null }) {
  const now = Date.now();
  sessionStorage.setItem(
    SESSION_KEY,
    JSON.stringify({ token, role, user, startedAt: now, lastActive: now }),
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
  return { token: session.token, role: session.role, user: session.user || null };
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
    request("/logout", {
      method: "POST",
      auth: false,
      headers: { Authorization: `Bearer ${session.token}` },
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

export async function loginUser(username, password) {
  try {
    const body = await request("/login", {
      method: "POST",
      body: { username, password },
      auth: false,
    });

    if (!body?.token || !body?.user) {
      throw new Error("The server returned an invalid login response.");
    }

    return {
      token: body.token,
      role: roleForUser(body.user),
      user: body.user,
    };
  } catch (error) {
    if (error.status === 401) {
      throw new Error("Invalid username or password.", { cause: error });
    }
    if (error.status === 429) {
      throw new Error(
        "Too many attempts. Please wait a few minutes and try again.",
        { cause: error },
      );
    }
    throw error;
  }
}

export async function registerUser({ name, email, phone, password }) {
  const body = await request("/register", {
    method: "POST",
    body: { name, email, phone, password },
    auth: false,
  });

  if (!body?.token || !body?.user) {
    throw new Error("The server returned an invalid registration response.");
  }

  return {
    token: body.token,
    role: roleForUser(body.user),
    user: body.user,
  };
}

export async function getDashboard() {
  const body = await request("/dashboard");
  if (!body?.success || !body?.data) {
    throw new Error(body?.message || "Could not load dashboard data.");
  }
  return body.data;
}

export const getAdminDashboard = getDashboard;

export async function getHomeData() {
  const body = await request("/home-data");
  if (!body?.success || !body?.data) {
    throw new Error(body?.message || "Could not load profile data.");
  }
  return body.data;
}

export async function getReceivedInterests(perPage = 20) {
  const body = await request(`/interests/received?per_page=${perPage}`);
  return body?.data || null;
}

export async function getSentInterests(perPage = 20) {
  const body = await request(`/interests/sent?per_page=${perPage}`);
  return body?.data || null;
}

export async function getProfiles(params = {}) {
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  const body = await request(`/profiles${query ? `?${query}` : ""}`);
  return body?.data || null;
}

export async function getMyProfile() {
  const body = await request("/my-profile");
  return body?.data || null;
}
