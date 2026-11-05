/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext } from "react";

export const AdminPermissionContext = createContext(null);

export function hasModulePermission(access, permission, currentPath, moduleKey) {
  if (!access) return false;
  if (access.is_super_admin) return true;

  const path = currentPath || access.path;
  return Boolean(access.modules?.some((module) =>
    (!moduleKey || module.key === moduleKey)
    && (!module.requires_super_admin || access.is_super_admin)
    && (!module.requires_manage || module.permissions?.includes("manage"))
    && (module.permissions?.includes(permission) || module.permissions?.includes("manage"))
    && (moduleKey || (module.routes || [module.route]).some((route) =>
      route && (path === route || (route !== "/admin" && path.startsWith(`${route}/`))),
    ))
  ));
}

export function useAdminPermission(permission, currentPath, moduleKey) {
  const access = useContext(AdminPermissionContext);
  return hasModulePermission(access, permission, currentPath, moduleKey);
}

export function PermissionGate({ permission, currentPath, moduleKey, children }) {
  return useAdminPermission(permission, currentPath, moduleKey) ? children : null;
}
