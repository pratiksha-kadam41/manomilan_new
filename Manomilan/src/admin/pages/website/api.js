import { request } from "../../../api";
import { clearPublicWebsiteSettingsCache } from "../../../website/api.js";

const saved = (promise) =>
  promise.then((response) => {
    clearPublicWebsiteSettingsCache();
    return response;
  });

export const getWebsiteOverview = () => request("/website-management/overview");

export const getUpcomingEventsSettings = () => request("/website-management/upcoming-events");

export const saveUpcomingEventsSettings = (body) =>
  saved(request("/website-management/upcoming-events", { method: "PUT", body }));

export const getDowntimeSettings = () => request("/website-management/downtime");

export const saveDowntimeSettings = (body) =>
  saved(request("/website-management/downtime", { method: "PUT", body }));

export const getMaintenanceSettings = () => request("/website-management/maintenance");

export const saveMaintenanceSettings = (body) =>
  saved(request("/website-management/maintenance", { method: "PUT", body }));
