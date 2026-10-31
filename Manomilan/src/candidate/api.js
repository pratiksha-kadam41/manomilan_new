import { apiMediaUrl, request } from "../api";
import { updateCachedUserProfile } from "../auth";

const normalizeCandidateMedia = (value, key = "") => {
  if (Array.isArray(value)) {
    return key.endsWith("_photo_urls")
      ? value.map(apiMediaUrl)
      : value.map((item) => normalizeCandidateMedia(item));
  }
  if (!value || typeof value !== "object") {
    return key === "photo_url" || key === "file_url" || key.endsWith("_photo_url")
      ? apiMediaUrl(value)
      : value;
  }

  return Object.fromEntries(
    Object.entries(value).map(([field, item]) => [
      field,
      normalizeCandidateMedia(item, field),
    ]),
  );
};

const data = (body) => normalizeCandidateMedia(body?.data ?? null);
let candidateProfileCache;
let candidateProfileCachedAt = 0;
let matchesCache = new Map();
let matchesRequestCache = new Map();
let matchesCacheVersion = 0;
let matchCache = new Map();
let matchRequestCache = new Map();

const CANDIDATE_PROFILE_CACHE_MS = 5 * 60 * 1000;
const MATCHES_CACHE_MS = 30 * 1000;
const DASHBOARD_CACHE_KEY = "candidate_dashboard_cache";
const DASHBOARD_CACHE_MS = 60 * 60 * 1000;
let dashboardRequest = null;

export const readDashboardCache = () => {
  try {
    const cached = JSON.parse(sessionStorage.getItem(DASHBOARD_CACHE_KEY));
    if (cached?.savedAt && Date.now() - cached.savedAt < DASHBOARD_CACHE_MS) {
      return normalizeCandidateMedia(cached.data || null);
    }
  } catch {
    sessionStorage.removeItem(DASHBOARD_CACHE_KEY);
  }
  return null;
};

export const getCandidateProfile = async () => {
  const now = Date.now();
  if (candidateProfileCache && now - candidateProfileCachedAt < CANDIDATE_PROFILE_CACHE_MS) {
    return candidateProfileCache;
  }
  if (!candidateProfileCache && candidateProfileCachedAt === -1) {
    return null;
  }
  if (!candidateProfileCache || now - candidateProfileCachedAt >= CANDIDATE_PROFILE_CACHE_MS) {
    if (!getCandidateProfile.pending) {
      getCandidateProfile.pending = request("/candidate/profile").then((body) => {
        const result = data(body);
        candidateProfileCache = result || null;
        candidateProfileCachedAt = Date.now();
        if (result?.profile) updateCachedUserProfile(result.profile);
        return result;
      }).finally(() => {
        getCandidateProfile.pending = null;
      });
    }
    return getCandidateProfile.pending;
  }
  return candidateProfileCache;
};

export const invalidateCandidateProfileCache = () => {
  candidateProfileCache = null;
  candidateProfileCachedAt = 0;
};

export const getDashboard = async ({ force = false } = {}) => {
  if (!force) {
    const cached = readDashboardCache();
    if (cached) return cached;
  }
  if (!dashboardRequest) {
    dashboardRequest = request("/candidate/dashboard").then((body) => data(body)).finally(() => {
      dashboardRequest = null;
    });
  }
  const result = await dashboardRequest;
  sessionStorage.setItem(DASHBOARD_CACHE_KEY, JSON.stringify({
    savedAt: Date.now(),
    data: result,
  }));
  return result;
};

export const createCandidateProfile = async (payload) => {
  invalidateCandidateProfileCache();
  return data(await request("/candidate/profile", { method: "POST", body: payload }));
};

export const updateCandidateProfile = async (payload) => {
  invalidateCandidateProfileCache();
  return data(await request("/candidate/profile", { method: "PUT", body: payload }));
};

export const uploadCandidatePhoto = async (file) => {
  const form = new FormData();
  form.append("photo", file);
  invalidateCandidateProfileCache();
  const result = data(await request("/candidate/profile/photo", { method: "POST", body: form }));
  return result;
};

export const removeCandidatePhoto = async () =>
  (invalidateCandidateProfileCache(), data(await request("/candidate/profile/photo", { method: "DELETE" })));

export const uploadCandidateBodyPhotos = async (photos) => {
  const form = new FormData();
  Object.entries(photos).forEach(([field, file]) => {
    if (file) form.append(field, file);
  });
  invalidateCandidateProfileCache();
  return data(await request("/candidate/profile/body-photos", { method: "POST", body: form }));
};

export const uploadCandidateDocuments = async (files) => {
  const form = new FormData();
  Object.entries(files).forEach(([key, file]) => {
    if (file) form.append(key, file);
  });
  return data(await request("/candidate/profile/documents", { method: "POST", body: form }));
};

export const removeCandidateDocument = async (key) =>
  data(await request(`/candidate/profile/documents/${key}`, { method: "DELETE" }));

export const getPreferences = async () => data(await request("/candidate/preferences"));

export const savePreferences = async (payload) =>
  data(await request("/candidate/preferences", { method: "PUT", body: payload }));

export const getMatches = async (params = {}, { signal } = {}) => {
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  const cacheKey = query || "__default__";
  const cached = matchesCache.get(cacheKey);
  if (cached && Date.now() - cached.cachedAt < MATCHES_CACHE_MS) return cached.result;
  if (cached) matchesCache.delete(cacheKey);

  const cacheVersion = matchesCacheVersion;
  const path = `/candidate/matches${query ? `?${query}` : ""}`;
  if (signal) {
    const result = data(await request(path, { signal }));
    if (cacheVersion === matchesCacheVersion) {
      matchesCache.set(cacheKey, { result, cachedAt: Date.now() });
    }
    return result;
  }

  if (!matchesRequestCache.has(cacheKey)) {
    matchesRequestCache.set(cacheKey, request(path)
      .then((body) => data(body))
      .then((result) => {
        if (cacheVersion === matchesCacheVersion) {
          matchesCache.set(cacheKey, { result, cachedAt: Date.now() });
        }
        return result;
      })
      .finally(() => matchesRequestCache.delete(cacheKey)));
  }
  return matchesRequestCache.get(cacheKey);
};

export const getMatch = async (id) => {
  const cacheKey = String(id);
  if (matchCache.has(cacheKey)) return matchCache.get(cacheKey);
  if (!matchRequestCache.has(cacheKey)) {
    matchRequestCache.set(cacheKey, request(`/candidate/matches/${cacheKey}`)
      .then((body) => data(body))
      .then((result) => {
        matchCache.set(cacheKey, result);
        return result;
      })
      .finally(() => matchRequestCache.delete(cacheKey)));
  }
  const result = await matchRequestCache.get(cacheKey);
  sessionStorage.removeItem(DASHBOARD_CACHE_KEY);
  return result;
};

export const invalidateMatchesCache = () => {
  matchesCacheVersion += 1;
  matchesCache.clear();
  matchCache.clear();
};

export const getInterests = async (mode = "sent", params = {}) => {
  const path = mode === "received" ? "/candidate/interests/received" : "/candidate/interests/sent";
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  return data(await request(`${path}${query ? `?${query}` : ""}`));
};

export const sendInterest = async (profileId) => {
  const result = data(await request("/candidate/interests", {
    method: "POST",
    body: { to_profile_id: profileId },
  }));
  invalidateMatchesCache();
  matchRequestCache.clear();
  return result;
};

export const respondToInterest = async (id, status) =>
  data(await request(`/candidate/interests/${id}/respond`, {
    method: "POST",
    body: { status },
  }));

export const getNotifications = async (params = {}) => {
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  return data(await request(`/candidate/notifications${query ? `?${query}` : ""}`));
};

export const getUnreadNotificationCount = async () =>
  data(await request("/candidate/notifications/unread-count"));

export const markNotificationRead = async (id) =>
  data(await request(`/candidate/notifications/${id}/read`, { method: "POST" }));

export const markAllNotificationsRead = async () =>
  data(await request("/candidate/notifications/read-all", { method: "POST" }));

export const deleteNotification = async (id) =>
  data(await request(`/candidate/notifications/${id}`, { method: "DELETE" }));
