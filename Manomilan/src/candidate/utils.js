export const cx = (...values) => values.filter(Boolean).join(" ");

export const text = (value, fallback = "") => {
  if (value === null || value === undefined) return fallback;
  const str = String(value).trim();
  return str || fallback;
};

export const initials = (name = "") =>
  String(name)
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || "")
    .join("") || "M";

export const titleCase = (value = "") =>
  String(value)
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

export const statusLabel = (status) => {
  const value = String(status || "pending").toLowerCase();
  if (value === "rejected" || value === "declined") return "Declined";
  if (value === "accepted") return "Accepted";
  if (value === "verified") return "Verified";
  if (value === "not_eligible") return "Not eligible";
  return "Pending";
};

export const statusClass = (status) => {
  const value = String(status || "pending").toLowerCase();
  if (value === "rejected" || value === "declined") return "declined";
  if (value === "accepted" || value === "verified") return "accepted";
  if (value === "not_eligible") return "declined";
  return "pending";
};

export const timeAgo = (value) => {
  if (!value) return "";
  const diff = Date.now() - new Date(value).getTime();
  if (Number.isNaN(diff) || diff < 0) return "";
  const minutes = Math.floor(diff / 60000);
  if (minutes < 1) return "Just now";
  if (minutes < 60) return `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`;
  const days = Math.floor(hours / 24);
  if (days === 1) return "Yesterday";
  if (days < 7) return `${days} days ago`;
  return new Date(value).toLocaleDateString("en-IN", {
    day: "numeric",
    month: "short",
  });
};

export const formatDate = (value, fallback = "Not provided") => {
  if (!value) return fallback;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return fallback;
  return date.toLocaleDateString("en-IN", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};

export const fileSizeLabel = (bytes) => {
  if (!bytes) return "";
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export const humanGender = (value) => {
  const gender = String(value || "").toLowerCase();
  if (gender === "bride") return "Bride";
  if (gender === "groom") return "Groom";
  return "";
};

export const displayValue = (value, fallback = "Not provided") => {
  if (Array.isArray(value)) {
    const joined = value.filter(Boolean).join(", ");
    return joined || fallback;
  }
  if (typeof value === "boolean") return value ? "Yes" : "No";
  return text(value, fallback);
};

export const clampPercent = (value) =>
  Math.min(100, Math.max(0, Math.round(Number(value) || 0)));

export const dispatchNotificationsChanged = () => {
  window.dispatchEvent(new CustomEvent("candidate:notifications-updated"));
};

export const groupNotifications = (items = []) => {
  const groups = [];
  items.forEach((item) => {
    const date = item.created_at ? new Date(item.created_at) : null;
    const key = date && !Number.isNaN(date.getTime())
      ? date.toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" })
      : "Earlier";
    let group = groups.find((entry) => entry.label === key);
    if (!group) {
      group = { label: key, items: [] };
      groups.push(group);
    }
    group.items.push(item);
  });
  return groups;
};
