/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext } from "react";

export const AdminPermissionContext = createContext(null);

export function hasModulePermission(access, permission, currentPath) {
  if (!access) return false;
  if (access.is_super_admin) return true;

  const path = currentPath || access.path;
  return Boolean(access.modules?.some((module) =>
    (!module.requires_super_admin || access.is_super_admin)
    && (module.permissions?.includes(permission) || module.permissions?.includes("manage"))
    && (module.routes || [module.route]).some((route) =>
      route && (path === route || (route !== "/admin" && path.startsWith(`${route}/`))),
    )
  ));
}

export function useAdminPermission(permission, currentPath) {
  const access = useContext(AdminPermissionContext);
  return hasModulePermission(access, permission, currentPath);
}

export function PermissionGate({ permission, currentPath, children }) {
  return useAdminPermission(permission, currentPath) ? children : null;
}
