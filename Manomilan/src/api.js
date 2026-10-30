const API_URL = (
  import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"
).replace(/\/+$/, "");

const SESSION_KEY = "auth";

export class ApiError extends Error {
  constructor(message, status = 0, errors = null) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

export const apiUrl = API_URL;

const readStoredSession = () => {
  try {
    return JSON.parse(sessionStorage.getItem(SESSION_KEY));
  } catch {
    return null;
  }
};

export const getToken = () => readStoredSession()?.token || "";

const firstValidationError = (body) => {
  const errors = body?.errors;
  if (errors && typeof errors === "object") {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first.length) return first[0];
    if (typeof first === "string") return first;
  }
  return "";
};

const expireSession = () => {
  sessionStorage.removeItem(SESSION_KEY);
  if (window.location.pathname !== "/login") {
    window.location.assign("/login?expired=1");
  }
};

const parseBody = async (response) => {
  if (response.status === 204) return null;

  const contentType = response.headers.get("content-type") || "";
  if (contentType.includes("application/json")) {
    return response.json().catch(() => null);
  }

  return response.text().catch(() => "");
};

export async function request(
  path,
  { method = "GET", body, headers = {}, auth = true, raw = false } = {},
) {
  const url = /^https?:\/\//i.test(path)
    ? path
    : `${API_URL}${path.startsWith("/") ? path : `/${path}`}`;

  const token = getToken();
  const finalHeaders = { Accept: "application/json", ...headers };

  if (auth && token) {
    finalHeaders.Authorization = `Bearer ${token}`;
  }

  const options = { method, headers: finalHeaders };
  const controller = new AbortController();
  const timeoutId = window.setTimeout(() => controller.abort(), 30000);
  options.signal = controller.signal;

  if (body !== undefined && body !== null) {
    if (body instanceof FormData) {
      options.body = body;
    } else {
      finalHeaders["Content-Type"] = "application/json";
      options.body = JSON.stringify(body);
    }
  }

  let response;
  try {
    response = await fetch(url, options);
  } catch (error) {
    if (error?.name === "AbortError") {
      throw new ApiError("The server took too long to respond. Please try again.", 0);
    }
    throw new ApiError(
      "Cannot reach the server. Please check your connection and try again.",
      0,
    );
  } finally {
    window.clearTimeout(timeoutId);
  }

  if (raw) {
    if (response.status === 401 && auth && token) expireSession();
    if (!response.ok) {
      const data = await parseBody(response);
      throw new ApiError(
        firstValidationError(data) || data?.message || "Request failed.",
        response.status,
        data?.errors,
      );
    }
    return response;
  }

  const data = await parseBody(response);

  if (response.status === 401 && auth && token) {
    expireSession();
  }

  if (!response.ok) {
    throw new ApiError(
      firstValidationError(data) ||
        data?.message ||
        (response.status === 401
          ? "Your session has expired. Please sign in again."
          : "Something went wrong. Please try again."),
      response.status,
      data?.errors,
    );
  }

  return data;
}

export async function download(path, fallbackName = "download") {
  const response = await request(path, { raw: true });
  const disposition = response.headers.get("content-disposition") || "";
  const match = disposition.match(/filename="?([^";]+)"?/i);
  const filename = match?.[1] || fallbackName;

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = Object.assign(document.createElement("a"), {
    href: url,
    download: filename,
  });
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
