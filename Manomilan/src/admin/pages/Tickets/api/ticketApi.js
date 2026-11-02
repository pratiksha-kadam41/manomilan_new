import { request } from "../../../../api";

export const listTickets = ({ search = "", page = 1, inactive = false } = {}) => {
  const params = new URLSearchParams({
    per_page: "10",
    page: String(page),
    active: inactive ? "0" : "1",
  });
  if (search) params.set("search", search);
  return request(`/tickets?${params}`);
};

export const getTicketMeta = () => request("/tickets/meta");

export const getTicket = (id) => request(`/tickets/${id}`);

export const createTicket = (formData) =>
  request("/tickets", { method: "POST", body: formData });

export const updateTicket = (id, formData) => {
  formData.append("_method", "PUT");
  return request(`/tickets/${id}`, { method: "POST", body: formData });
};

export const deactivateTicket = (id) =>
  request(`/tickets/${id}`, { method: "DELETE" });

export const activateTicket = (id) =>
  request(`/tickets/${id}/activate`, { method: "PATCH" });
