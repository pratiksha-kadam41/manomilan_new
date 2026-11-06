const configuredApiUrl = import.meta.env.VITE_API_URL || "/api";
const API_URL = (() => {
  if (!/^https?:\/\//i.test(configuredApiUrl)) {
    return configuredApiUrl.replace(/\/+$/, "");
  }

  const url = new URL(configuredApiUrl);
  const loopbackHosts = new Set(["localhost", "127.0.0.1", "::1"]);
  if (
    typeof window !== "undefined" &&
    loopbackHosts.has(url.hostname) &&
    !loopbackHosts.has(window.location.hostname)
  ) {
    url.hostname = window.location.hostname;
  }

  return url.toString().replace(/\/+$/, "");
})();

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

const PHOTO_OBJECT_KEYS = [
  "url",
  "photo_url",
  "photoUrl",
  "profile_photo",
  "profilePhoto",
  "profile_photo_url",
  "image_url",
  "image",
  "path",
  "photo_path",
  "file_path",
  "src",
  "photo",
  "additional_photo_urls",
  "additional_photos",
  "additionalPhotos",
  "photos",
  "data",
];

function firstPhoto(value) {
  if (typeof value === "string") {
    const photo = value.trim();
    if (!photo || /^(?:null|undefined|\[\]|\{\})$/i.test(photo)) return "";

    if (photo.startsWith("[") || photo.startsWith("{") || photo.startsWith('"')) {
      try {
        const parsed = JSON.parse(photo);
        if (parsed !== photo) return firstPhoto(parsed);
      } catch {
        return "";
      }
    }

    return photo;
  }

  if (Array.isArray(value)) {
    for (const item of value) {
      const photo = firstPhoto(item);
      if (photo) return photo;
    }
    return "";
  }

  if (value && typeof value === "object") {
    for (const key of PHOTO_OBJECT_KEYS) {
      if (key in value) {
        const photo = firstPhoto(value[key]);
        if (photo) return photo;
      }
    }
    for (const [key, item] of Object.entries(value)) {
      if (/^\d+$/.test(key)) {
        const photo = firstPhoto(item);
        if (photo) return photo;
      }
    }
  }

  return "";
}

export function getProfilePhoto(profile, primaryFields = []) {
  if (!profile || typeof profile !== "object") return "";

  const candidateProfiles = [profile, profile.profile].filter(
    (candidate) => candidate && typeof candidate === "object",
  );
  for (const candidate of candidateProfiles) {
    for (const field of primaryFields) {
      const photo = firstPhoto(candidate[field]);
      if (photo) return photo;
    }
  }

  for (const candidate of candidateProfiles) {
    const additionalPhotos = [
      candidate.additional_photo_urls,
      candidate.additional_photos,
      candidate.additionalPhotos,
      candidate.photos?.additional_photo_urls,
      candidate.photos?.additional_photos,
      candidate.photos?.additionalPhotos,
    ];
    for (const value of additionalPhotos) {
      const photo = firstPhoto(value);
      if (photo) return photo;
    }
  }

  return "";
}

export function apiMediaUrl(value) {
  const mediaPath = String(value || "").trim();
  if (!mediaPath) return "";

  const apiOrigin = new URL(API_URL, window.location.origin).origin;
  const knownUploadFolders = [
    "profile_photos/",
    "full_body_photos/",
    "half_body_photos/",
    "selfie_photos/",
    "melava_candidate_photos/",
    "melava_additional_photos/",
  ];
  const mediaFolders = /^\/(?:storage\/)?candidate(?:_images|_documents)\//i;

  try {
    const parsedUrl = new URL(mediaPath, apiOrigin);
    let path = parsedUrl.pathname;

    if (/^https?:/i.test(mediaPath)
      && !/^\/(?:storage|uploads|candidate|profile_photos|additional_photos)\//i.test(path)
      && !mediaFolders.test(path)) {
      return mediaPath;
    }

    const storedPath = path.replace(/^\/storage\//i, "").replace(/^\/+/, "");
    if (/^\/success_stories\//i.test(path)) {
      path = `/storage${path}`;
    } else if (/^candidate\/documents\//i.test(storedPath)) {
      path = `/storage/candidate_documents/${storedPath.split("/").pop()}`;
    } else if (/^candidate\/(?:profile_photos|body_photos|photos)\//i.test(storedPath)) {
      path = `/storage/candidate_images/${storedPath.split("/").pop()}`;
    } else if (/^candidate_images\//i.test(storedPath)) {
      path = `/storage/${storedPath}`;
    } else if (/^candidate_documents\//i.test(storedPath)) {
      path = `/storage/${storedPath}`;
    } else if (/^uploads\/(?:melava_documents|melava_birth_docs|melava_additional_photos|melava_qr)\//i.test(storedPath)) {
      path = `/${storedPath}`;
    } else if (/^uploads\/profile_photos\//i.test(storedPath)) {
      path = `/storage/${storedPath.replace(/^uploads\//i, "")}`;
    } else if (/^uploads\//i.test(storedPath)) {
      path = `/storage/${storedPath}`;
    } else if (/^melava_additional_photos\//i.test(storedPath)) {
      path = `/uploads/${storedPath}`;
    } else if (/^additional_photos\//i.test(storedPath)) {
      path = `/storage/${storedPath}`;
    } else if (/^profile_photos\//i.test(storedPath)) {
      path = `/storage/${storedPath}`;
    } else if (/^candidate\//i.test(storedPath)) {
      path = `/storage/${storedPath}`;
    } else if (knownUploadFolders.some((folder) => path.startsWith(`/${folder}`))) {
      path = `/uploads${path}`;
    }

    return `${apiOrigin}${path}${parsedUrl.search}${parsedUrl.hash}`;
  } catch {
    return mediaPath;
  }
}

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
  { method = "GET", body, headers = {}, auth = true, raw = false, signal: externalSignal } = {},
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
  const abortForExternalSignal = () => controller.abort();
  if (externalSignal?.aborted) {
    controller.abort();
  } else {
    externalSignal?.addEventListener("abort", abortForExternalSignal, { once: true });
  }
  const timeoutId = window.setTimeout(() => controller.abort(), 60000);
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
      if (externalSignal?.aborted) throw error;
      throw new ApiError("The server took too long to respond. Please try again.", 0);
    }
    throw new ApiError(
      "Cannot reach the server. Please check your connection and try again.",
      0,
    );
  } finally {
    window.clearTimeout(timeoutId);
    externalSignal?.removeEventListener("abort", abortForExternalSignal);
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
