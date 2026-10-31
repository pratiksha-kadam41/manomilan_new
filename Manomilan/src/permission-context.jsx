/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext } from "react";

export const AdminPermissionContext = createContext(null);

export function useAdminPermission(permission, currentPath) {
  const access = useContext(AdminPermissionContext);
  if (!access) return false;
  if (access.is_super_admin) return true;

  const path = currentPath || access.path;
  const module = access.modules?.find((item) =>
    (item.routes || [item.route]).some((route) =>
      route && (path === route || (route !== "/admin" && path.startsWith(`${route}/`))),
    ),
  );
  return Boolean(module?.permissions?.includes(permission) || module?.permissions?.includes("manage"));
}

export function PermissionGate({ permission, currentPath, children }) {
  return useAdminPermission(permission, currentPath) ? children : null;
}
