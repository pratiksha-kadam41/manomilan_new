export const MAX_FILE_MB = 10;

export const FALLBACK_TICKET_OPTIONS = {
  categories: ["Requirement", "Bug", "Enhancement", "Support", "Other"],
  item_types: ["Development", "Bug Fix", "Enhancement", "Support", "Testing"],
  priorities: ["Low", "Medium", "High", "Critical"],
  severities: ["Low", "Medium", "High", "Critical"],
};

export const emptyTicketMeta = () => ({
  modules: [],
  ...FALLBACK_TICKET_OPTIONS,
});
