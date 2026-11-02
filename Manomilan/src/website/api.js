import { request } from "../api.js";

const CACHE_MS = 30000;
let cached = null;
let cachedAt = 0;
let pending = null;

const fallback = {
  upcoming_events: { enabled: false },
  downtime: { enabled: false, active: false },
  maintenance: { enabled: false, active: false },
};

export async function getPublicWebsiteSettings({ force = false } = {}) {
  const now = Date.now();
  if (!force && cached && now - cachedAt < CACHE_MS) return cached;
  if (pending) return pending;

  pending = request("/website/settings", { auth: false })
    .then((response) => {
      cached = response?.data || fallback;
      cachedAt = Date.now();
      return cached;
    })
    .catch(() => {
      cached = fallback;
      cachedAt = Date.now();
      return cached;
    })
    .finally(() => {
      pending = null;
    });

  return pending;
}

export function clearPublicWebsiteSettingsCache() {
  cached = null;
  cachedAt = 0;
  pending = null;
}

export function formatRemaining(seconds) {
  const total = Math.max(0, Number(seconds) || 0);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const secs = Math.floor(total % 60);
  return [hours, minutes, secs].map((part) => String(part).padStart(2, "0")).join(":");
}
