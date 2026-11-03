import { apiMediaUrl, request } from "../api";
import { getSession, updateCachedUserProfile } from "../auth";

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
let candidateFeedCache = null;
let matchCache = new Map();
let matchRequestCache = new Map();

const CANDIDATE_PROFILE_CACHE_MS = 5 * 60 * 1000;
const MATCHES_CACHE_MS = 5 * 60 * 1000;
const DASHBOARD_CACHE_KEY = "candidate_dashboard_cache";
const DASHBOARD_CACHE_MS = 60 * 60 * 1000;
const CANDIDATE_QUERY_CACHE_PREFIX = "candidate_query_cache:";
const CANDIDATE_QUERY_CACHE_MS = 5 * 60 * 1000;
let dashboardRequest = null;

const queryString = (params) => new URLSearchParams(
  Object.entries(params).filter(([, value]) => value !== "" && value != null),
).toString();

const readQueryCache = (key, allowStale = false) => {
  try {
    const user = getSession()?.user;
    const namespace = user?.id || user?.username || "session";
    const storageKey = `${CANDIDATE_QUERY_CACHE_PREFIX}${encodeURIComponent(namespace)}:${key}`;
    const cached = JSON.parse(sessionStorage.getItem(storageKey));
    if (
      cached?.savedAt &&
      (allowStale || Date.now() - cached.savedAt < CANDIDATE_QUERY_CACHE_MS)
    ) {
      return cached.data;
    }
  } catch {
    return null;
  }
  return null;
};

const writeQueryCache = (key, result) => {
  try {
    const user = getSession()?.user;
    const namespace = user?.id || user?.username || "session";
    sessionStorage.setItem(`${CANDIDATE_QUERY_CACHE_PREFIX}${encodeURIComponent(namespace)}:${key}`, JSON.stringify({
      savedAt: Date.now(),
      data: result,
    }));
  } catch {
    return;
  }
};

const clearQueryCache = (prefix) => {
  try {
    const user = getSession()?.user;
    const namespace = user?.id || user?.username || "session";
    const keys = [];
    for (let index = 0; index < sessionStorage.length; index += 1) {
      const key = sessionStorage.key(index);
      if (key?.startsWith(`${CANDIDATE_QUERY_CACHE_PREFIX}${encodeURIComponent(namespace)}:${prefix}`)) keys.push(key);
    }
    keys.forEach((key) => sessionStorage.removeItem(key));
  } catch {
    return;
  }
};

const interestCacheKey = (mode = "sent", params = {}) =>
  `interests:${mode}:${queryString(params)}`;
const notificationCacheKey = (params = {}) => `notifications:${queryString(params)}`;

export const getCachedMatches = (params = {}) => {
  const query = queryString(params);
  const cached = matchesCache.get(query || "__default__");
  if (cached && Date.now() - cached.cachedAt < MATCHES_CACHE_MS) return cached.result;
  return readQueryCache(`matches:${query}`, true);
};

export const getCachedCandidateFeed = () => (
  candidateFeedCache && Date.now() - candidateFeedCache.cachedAt < MATCHES_CACHE_MS
    ? candidateFeedCache.result
    : null
);

export const cacheCandidateFeed = (filters, result, append = false) => {
  const previous = candidateFeedCache?.result;
  const seen = new Set(append ? (previous?.matches || []).map((item) => String(item.id)) : []);
  const matches = append
    ? [...(previous?.matches || []), ...(result?.matches || []).filter((item) => {
      const id = String(item.id);
      if (seen.has(id)) return false;
      seen.add(id);
      return true;
    })]
    : result?.matches || [];
  candidateFeedCache = {
    cachedAt: Date.now(),
    result: {
      ...result,
      matches,
      filters: append
        ? (result?.filters?.length ? result.filters : previous?.filters || {})
        : result?.filters || {},
      feed_filters: filters,
    },
  };
};

export const getCachedInterests = (mode = "sent", params = {}) =>
  readQueryCache(interestCacheKey(mode, params), true);

export const getCachedNotifications = (params = {}) =>
  readQueryCache(notificationCacheKey(params), true);

export const peekCandidateProfile = () => (
  candidateProfileCache && Date.now() - candidateProfileCachedAt < CANDIDATE_PROFILE_CACHE_MS
    ? candidateProfileCache
    : null
);

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
  dashboardRequest = null;
  invalidateMatchesCache();
  try {
    sessionStorage.removeItem(DASHBOARD_CACHE_KEY);
  } catch {
    clearQueryCache("");
    return;
  }
  clearQueryCache("");
};

const notifyCandidateProfileChanged = (result) => {
  if (result?.profile) {
    window.dispatchEvent(new CustomEvent("candidate:profile-updated", {
      detail: { profile: result.profile },
    }));
  }
  return result;
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
  const result = data(await request("/candidate/profile", { method: "POST", body: payload }));
  return notifyCandidateProfileChanged(result);
};

export const updateCandidateProfile = async (payload) => {
  invalidateCandidateProfileCache();
  return notifyCandidateProfileChanged(
    data(await request("/candidate/profile", { method: "PUT", body: payload })),
  );
};

export const uploadCandidatePhoto = async (file) => {
  const form = new FormData();
  form.append("photo", file);
  invalidateCandidateProfileCache();
  return notifyCandidateProfileChanged(
    data(await request("/candidate/profile/photo", { method: "POST", body: form })),
  );
};

export const removeCandidatePhoto = async () =>
  (invalidateCandidateProfileCache(), notifyCandidateProfileChanged(
    data(await request("/candidate/profile/photo", { method: "DELETE" })),
  ));

export const uploadCandidateBodyPhotos = async (photos) => {
  const form = new FormData();
  Object.entries(photos).forEach(([field, file]) => {
    if (file) form.append(field, file);
  });
  invalidateCandidateProfileCache();
  return notifyCandidateProfileChanged(
    data(await request("/candidate/profile/body-photos", { method: "POST", body: form })),
  );
};

export const uploadCandidateAdditionalPhotos = async (photos) => {
  const form = new FormData();
  photos.forEach((file) => form.append("photos[]", file));
  invalidateCandidateProfileCache();
  return notifyCandidateProfileChanged(
    data(await request("/candidate/profile/additional-photos", { method: "POST", body: form })),
  );
};

export const uploadCandidateDocuments = async (files) => {
  const form = new FormData();
  Object.entries(files).forEach(([key, file]) => {
    if (file) form.append(key, file);
  });
  return notifyCandidateProfileChanged(
    data(await request("/candidate/profile/documents", { method: "POST", body: form })),
  );
};

export const removeCandidateDocument = async (key) =>
  notifyCandidateProfileChanged(
    data(await request(`/candidate/profile/documents/${key}`, { method: "DELETE" })),
  );

export const getPreferences = async () => data(await request("/candidate/preferences"));

export const savePreferences = async (payload) =>
  notifyCandidateProfileChanged(
    data(await request("/candidate/preferences", { method: "PUT", body: payload })),
  );

export const getMatches = async (params = {}, { signal } = {}) => {
  const query = queryString(params);
  const cacheKey = query || "__default__";
  const cacheable = !params.feed || Number(params.page || 1) === 1;
  const cached = matchesCache.get(cacheKey);
  if (cacheable && cached && Date.now() - cached.cachedAt < MATCHES_CACHE_MS) return cached.result;
  if (cached) matchesCache.delete(cacheKey);
  const persisted = cacheable ? readQueryCache(`matches:${query}`) : null;
  if (persisted) {
    matchesCache.set(cacheKey, { result: persisted, cachedAt: Date.now() });
    return persisted;
  }

  const cacheVersion = matchesCacheVersion;
  const path = `/candidate/matches${query ? `?${query}` : ""}`;
  if (signal) {
    const result = data(await request(path, { signal }));
    if (cacheable && cacheVersion === matchesCacheVersion) {
      matchesCache.set(cacheKey, { result, cachedAt: Date.now() });
      writeQueryCache(`matches:${query}`, result);
    }
    return result;
  }

  if (!matchesRequestCache.has(cacheKey)) {
    matchesRequestCache.set(cacheKey, request(path)
      .then((body) => data(body))
      .then((result) => {
        if (cacheable && cacheVersion === matchesCacheVersion) {
          matchesCache.set(cacheKey, { result, cachedAt: Date.now() });
          writeQueryCache(`matches:${query}`, result);
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
  candidateFeedCache = null;
  matchCache.clear();
  clearQueryCache("matches:");
};

export const getInterests = async (mode = "sent", params = {}) => {
  const path = mode === "received" ? "/candidate/interests/received" : "/candidate/interests/sent";
  const query = queryString(params);
  const cacheKey = interestCacheKey(mode, params);
  const cached = readQueryCache(cacheKey);
  if (cached) return cached;
  const result = data(await request(`${path}${query ? `?${query}` : ""}`));
  writeQueryCache(cacheKey, result);
  return result;
};

export const sendInterest = async (profileId) => {
  const result = data(await request("/candidate/interests", {
    method: "POST",
    body: { to_profile_id: profileId },
  }));
  invalidateMatchesCache();
  clearQueryCache("interests:");
  matchRequestCache.clear();
  return result;
};

export const respondToInterest = async (id, status) => {
  const result = data(await request(`/candidate/interests/${id}/respond`, {
    method: "POST",
    body: { status },
  }));
  clearQueryCache("interests:");
  return result;
};

export const getNotifications = async (params = {}) => {
  const query = queryString(params);
  const cacheKey = notificationCacheKey(params);
  const cached = readQueryCache(cacheKey);
  if (cached) return cached;
  const result = data(await request(`/candidate/notifications${query ? `?${query}` : ""}`));
  writeQueryCache(cacheKey, result);
  return result;
};

export const getUnreadNotificationCount = async () => {
  const cacheKey = "notifications:unread-count";
  const cached = readQueryCache(cacheKey);
  if (cached) return cached;
  const result = data(await request("/candidate/notifications/unread-count"));
  writeQueryCache(cacheKey, result);
  return result;
};

export const markNotificationRead = async (id) => {
  const result = data(await request(`/candidate/notifications/${id}/read`, { method: "POST" }));
  clearQueryCache("notifications:");
  return result;
};

export const markAllNotificationsRead = async () => {
  const result = data(await request("/candidate/notifications/read-all", { method: "POST" }));
  clearQueryCache("notifications:");
  return result;
};

export const deleteNotification = async (id) => {
  const result = data(await request(`/candidate/notifications/${id}`, { method: "DELETE" }));
  clearQueryCache("notifications:");
  return result;
};
