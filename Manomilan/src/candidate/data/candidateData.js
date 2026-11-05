export const menuItems = [
  { label: "Dashboard", path: "/candidate", icon: "home", requiresComplete: true },
  { label: "My Profile", path: "/candidate/profile", icon: "user", requiresComplete: false },
  { label: "Find Matches", path: "/candidate/matches", icon: "search", requiresComplete: true },
  { label: "Interests Received", path: "/candidate/interests-received", icon: "inbox", requiresComplete: true },
  { label: "Interests Sent", path: "/candidate/interests-sent", icon: "send", requiresComplete: true },
  { label: "Melava Receipts", path: "/candidate/melava-receipts", icon: "receipt", requiresComplete: true },
];

export const accountItems = [
  { label: "Notifications", path: "/candidate/notifications", icon: "bell", requiresComplete: true },
  { label: "Change Password", path: "/candidate/change-password", icon: "key", requiresComplete: false },
];
