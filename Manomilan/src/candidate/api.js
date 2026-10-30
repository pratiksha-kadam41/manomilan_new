import { request } from "../api";

const data = (body) => body?.data ?? null;

export const getDashboard = async () => data(await request("/candidate/dashboard"));

export const getCandidateProfile = async () =>
  data(await request("/candidate/profile"));

export const createCandidateProfile = async (payload) =>
  data(await request("/candidate/profile", { method: "POST", body: payload }));

export const updateCandidateProfile = async (payload) =>
  data(await request("/candidate/profile", { method: "PUT", body: payload }));

export const uploadCandidatePhoto = async (file) => {
  const form = new FormData();
  form.append("photo", file);
  return data(await request("/candidate/profile/photo", { method: "POST", body: form }));
};

export const removeCandidatePhoto = async () =>
  data(await request("/candidate/profile/photo", { method: "DELETE" }));

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

export const getMatches = async (params = {}) => {
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  return data(await request(`/candidate/matches${query ? `?${query}` : ""}`));
};

export const getMatch = async (id) => data(await request(`/candidate/matches/${id}`));

export const getInterests = async (mode = "sent", params = {}) => {
  const path = mode === "received" ? "/candidate/interests/received" : "/candidate/interests/sent";
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  return data(await request(`${path}${query ? `?${query}` : ""}`));
};

export const sendInterest = async (profileId) =>
  data(await request("/candidate/interests", {
    method: "POST",
    body: { to_profile_id: profileId },
  }));

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
