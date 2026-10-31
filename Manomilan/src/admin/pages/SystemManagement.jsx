import { useCallback, useEffect, useMemo, useState } from "react";
import { Download, Edit3, Plus, RefreshCw, Save, Trash2 } from "lucide-react";
import AdminLayout from "../components/AdminLayout";
import { download, request } from "../../api";
import "./system-management.css";

const TABS = ["Roles", "Modules", "Permissions", "User access", "Audit log"];

const messageFrom = (error) =>
  error?.status === 403 ? "You do not have permission to manage system settings." : error?.message || "Request failed.";

export default function SystemManagement() {
  const [activeTab, setActiveTab] = useState(TABS[0]);
  const [snapshot, setSnapshot] = useState(null);
  const [selectedRole, setSelectedRole] = useState("");
  const [permissionModules, setPermissionModules] = useState([]);
  const [permissionLastUpdated, setPermissionLastUpdated] = useState(null);
  const [users, setUsers] = useState([]);
  const [auditLogs, setAuditLogs] = useState([]);
  const [auditMeta, setAuditMeta] = useState(null);
  const [auditActions, setAuditActions] = useState([]);
  const [query, setQuery] = useState("");
  const [auditFilters, setAuditFilters] = useState({ q: "", action: "", module_id: "", from: "", to: "", page: "1" });
  const [roleForm, setRoleForm] = useState({ name: "", user_type: "" });
  const [moduleForm, setModuleForm] = useState({ module_name: "", description: "", route: "", frontend_routes: "", icon: "", api_prefixes: "" });
  const [editingRole, setEditingRole] = useState(null);
  const [editRoleForm, setEditRoleForm] = useState({ name: "", user_type: "" });
  const [editingModule, setEditingModule] = useState(null);
  const [editModuleForm, setEditModuleForm] = useState({ module_name: "", description: "", route: "", frontend_routes: "", icon: "", api_prefixes: "" });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const loadSnapshot = useCallback(async ({ showLoading = true } = {}) => {
    if (showLoading) setLoading(true);
    setError("");
    try {
      const response = await request("/system");
      setSnapshot(response.data);
      setSelectedRole((current) => current || String(response.data.roles?.[0]?.id || ""));
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      if (showLoading) setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(loadSnapshot, 0);
    return () => window.clearTimeout(timer);
  }, [loadSnapshot]);

  const loadRolePermissions = useCallback(async () => {
    if (!selectedRole) return;
    try {
      const response = await request(`/system/roles/${selectedRole}/permissions`);
      setPermissionModules(response.data.modules || []);
      setPermissionLastUpdated(response.data.last_updated_at || null);
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  }, [selectedRole]);

  useEffect(() => {
    if (activeTab !== "Permissions") return undefined;
    const timer = window.setTimeout(loadRolePermissions, 0);
    return () => window.clearTimeout(timer);
  }, [activeTab, loadRolePermissions]);

  const loadUsers = useCallback(async () => {
    try {
      const response = await request(`/system/users${query ? `?q=${encodeURIComponent(query)}` : ""}`);
      setUsers(response.data.data || []);
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  }, [query]);

  useEffect(() => {
    if (activeTab !== "User access") return undefined;
    const timer = window.setTimeout(loadUsers, 0);
    return () => window.clearTimeout(timer);
  }, [activeTab, loadUsers]);

  const loadAudit = useCallback(async () => {
    const params = new URLSearchParams(
      Object.entries(auditFilters).filter(([, value]) => value),
    );
    try {
      const response = await request(`/system/audit-logs${params.size ? `?${params}` : ""}`);
      setAuditLogs((current) => Number(auditFilters.page) > 1
        ? [...current, ...(response.data.data || [])]
        : response.data.data || []);
      setAuditMeta(response.data);
      setAuditActions(response.filters?.actions || []);
    } catch (requestError) {
      setError(messageFrom(requestError));
    }
  }, [auditFilters]);

  useEffect(() => {
    if (activeTab !== "Audit log") return undefined;
    const timer = window.setTimeout(loadAudit, 0);
    return () => window.clearTimeout(timer);
  }, [activeTab, loadAudit]);

  const permissionIds = useMemo(
    () => permissionModules.flatMap((module) =>
      module.permissions.filter((permission) => permission.granted).map((permission) => permission.id),
    ),
    [permissionModules],
  );

  const run = async (operation, successMessage) => {
    setSaving(true);
    setError("");
    setNotice("");
    try {
      await operation();
      setNotice(successMessage);
      await loadSnapshot({ showLoading: false });
    } catch (requestError) {
      setError(messageFrom(requestError));
    } finally {
      setSaving(false);
    }
  };

  const createRole = (event) => {
    event.preventDefault();
    run(async () => {
      const response = await request("/system/roles", { method: "POST", body: roleForm });
      setRoleForm({ name: "", user_type: "" });
      setSelectedRole(String(response.data.id));
    }, "Role created.");
  };

  const createModule = (event) => {
    event.preventDefault();
    run(async () => {
      await request("/system/modules", {
        method: "POST",
        body: {
          ...moduleForm,
          frontend_routes: moduleForm.frontend_routes.split(",").map((route) => route.trim()).filter(Boolean),
          api_prefixes: moduleForm.api_prefixes.split(",").map((prefix) => prefix.trim()).filter(Boolean),
        },
      });
      setModuleForm({ module_name: "", description: "", route: "", frontend_routes: "", icon: "", api_prefixes: "" });
    }, "Module created.");
  };

  const togglePermission = (moduleId, permissionId, checked) => {
    setPermissionModules((modules) => modules.map((module) => module.id !== moduleId
      ? module
      : {
          ...module,
          permissions: module.permissions.map((permission) => permission.id === permissionId
            ? { ...permission, granted: checked }
            : permission),
        }));
  };

  const toggleModulePermissions = (module, checked) => {
    setPermissionModules((modules) => modules.map((item) => item.id !== module.id
      ? item
      : { ...item, permissions: item.permissions.map((permission) => ({ ...permission, granted: checked })) }));
  };

  const savePermissions = () => {
    if (!window.confirm("Save these permissions for the selected role?")) return;
    run(
      () => request(`/system/roles/${selectedRole}/permissions`, {
        method: "PUT",
        body: { permissions: permissionIds },
      }),
      "Role permissions saved.",
    );
  };

  const assignRole = (userId, roleId) => {
    run(
      () => request(`/system/users/${userId}/role`, { method: "PATCH", body: { role_id: Number(roleId) } }),
      "User role updated.",
    ).then(loadUsers);
  };

  const updateModuleStatus = (module) => run(
    () => request(`/system/modules/${module.id}`, {
      method: "PUT",
      body: { status: !module.status },
    }),
    "Module status updated.",
  );

  const updateRoleStatus = (role) => run(
    () => request(`/system/roles/${role.id}`, {
      method: "PUT",
      body: { is_active: !role.is_active },
    }),
    "Role status updated.",
  );

  const updateRoleDetails = (event) => {
    event.preventDefault();
    run(() => request(`/system/roles/${editingRole.id}`, {
      method: "PUT",
      body: editRoleForm,
    }), "Role updated.").then(() => setEditingRole(null));
  };

  const updateModuleDetails = (event) => {
    event.preventDefault();
    run(() => request(`/system/modules/${editingModule.id}`, {
      method: "PUT",
      body: {
        ...editModuleForm,
        frontend_routes: editModuleForm.frontend_routes.split(",").map((route) => route.trim()).filter(Boolean),
        api_prefixes: editModuleForm.api_prefixes.split(",").map((prefix) => prefix.trim()).filter(Boolean),
      },
    }), "Module updated.").then(() => setEditingModule(null));
  };

  const removeRole = (role) => {
    if (!window.confirm(`Delete the role “${role.name}”?`)) return;
    run(() => request(`/system/roles/${role.id}`, { method: "DELETE" }), "Role deleted.");
  };

  const removeModule = (module) => {
    if (!window.confirm(`Delete the module “${module.module_name}”?`)) return;
    run(() => request(`/system/modules/${module.id}`, { method: "DELETE" }), "Module deleted.");
  };

  const applyAuditFilters = (event) => {
    event.preventDefault();
    setAuditLogs([]);
    setAuditFilters((filters) => ({ ...filters, page: "1" }));
  };

  return (
    <AdminLayout currentPath="/admin/system-management" contentClassName="sm-content">
      <div className="sm-page">
        <header className="sm-heading">
          <div>
            <h1>System Management</h1>
            <p>Manage roles, module permissions, user access, and audit history.</p>
          </div>
          <button type="button" className="sm-button" onClick={loadSnapshot} disabled={loading}>
            <RefreshCw size={16} /> Refresh
          </button>
        </header>

        {error ? <div className="sm-alert error" role="alert">{error}</div> : null}
        {notice ? <div className="sm-alert success" role="status">{notice}</div> : null}
        {loading ? <div className="sm-empty">Loading system settings…</div> : null}

        {snapshot ? (
          <>
            <section className="sm-stats">
              {Object.entries(snapshot.statistics || {}).map(([key, value]) => (
                <article className="sm-stat" key={key}>
                  <span>{key.replaceAll("_", " ")}</span>
                  <strong>{value}</strong>
                </article>
              ))}
            </section>

            <nav className="sm-tabs" aria-label="System management sections">
              {TABS.map((tab) => (
                <button
                  type="button"
                  key={tab}
                  className={activeTab === tab ? "active" : ""}
                  onClick={() => { setActiveTab(tab); setError(""); setNotice(""); }}
                >
                  {tab}
                </button>
              ))}
            </nav>

            {activeTab === "Roles" ? (
              <section className="sm-panel">
                <h2>Roles</h2>
                <form className="sm-form inline" onSubmit={createRole}>
                  <label>Role name<input value={roleForm.name} onChange={(event) => setRoleForm({ ...roleForm, name: event.target.value })} required /></label>
                  <label>Role code<input value={roleForm.user_type} onChange={(event) => setRoleForm({ ...roleForm, user_type: event.target.value })} required /></label>
                  <button className="sm-button primary" disabled={saving}><Plus size={16} /> Add role</button>
                </form>
                {editingRole ? (
                  <form className="sm-form inline" onSubmit={updateRoleDetails}>
                    <label>Role name<input value={editRoleForm.name} onChange={(event) => setEditRoleForm({ ...editRoleForm, name: event.target.value })} required /></label>
                    <label>Role code<input value={editRoleForm.user_type} onChange={(event) => setEditRoleForm({ ...editRoleForm, user_type: event.target.value })} required /></label>
                    <button className="sm-button primary" disabled={saving}><Save size={16} /> Save role</button>
                    <button type="button" className="sm-button" onClick={() => setEditingRole(null)}>Cancel</button>
                  </form>
                ) : null}
                <div className="sm-table-wrap">
                  <table className="sm-table">
                    <thead><tr><th>Role</th><th>Code</th><th>Users</th><th>Granted permissions</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>{snapshot.roles.map((role) => (
                      <tr key={role.id}>
                        <td>{role.name}</td><td>{role.user_type}</td><td>{role.users_count}</td><td>{role.module_permissions_count}</td>
                        <td><span className={`sm-badge ${role.is_active ? "on" : "off"}`}>{role.is_active ? "Active" : "Inactive"}</span></td>
                        <td className="sm-actions">
                          <button type="button" className="sm-icon-button" title="Edit role" onClick={() => { setEditingRole(role); setEditRoleForm({ name: role.name, user_type: role.user_type }); }}><Edit3 size={16} /></button>
                          <button type="button" className="sm-button small" disabled={role.is_protected} onClick={() => updateRoleStatus(role)}>{role.is_active ? "Deactivate" : "Activate"}</button>
                          <button type="button" className="sm-icon-button danger" disabled={role.is_protected || role.users_count > 0} title="Delete role" onClick={() => removeRole(role)}><Trash2 size={16} /></button>
                        </td>
                      </tr>
                    ))}</tbody>
                  </table>
                </div>
              </section>
            ) : null}

            {activeTab === "Modules" ? (
              <section className="sm-panel">
                <h2>Modules</h2>
                <form className="sm-form inline" onSubmit={createModule}>
                  <label>Descriptive module name<input value={moduleForm.module_name} onChange={(event) => setModuleForm({ ...moduleForm, module_name: event.target.value })} required /></label>
                  <label>Description<input value={moduleForm.description} onChange={(event) => setModuleForm({ ...moduleForm, description: event.target.value })} /></label>
                  <label>Frontend route<input value={moduleForm.route} onChange={(event) => setModuleForm({ ...moduleForm, route: event.target.value })} placeholder="/admin/example" /></label>
                  <label>Additional frontend routes<input value={moduleForm.frontend_routes} onChange={(event) => setModuleForm({ ...moduleForm, frontend_routes: event.target.value })} placeholder="/admin/one, /admin/two" /></label>
                  <label>Icon name<input value={moduleForm.icon} onChange={(event) => setModuleForm({ ...moduleForm, icon: event.target.value })} /></label>
                  <label>API route prefixes<input value={moduleForm.api_prefixes} onChange={(event) => setModuleForm({ ...moduleForm, api_prefixes: event.target.value })} placeholder="/profiles, /profiles/{id}" /></label>
                  <button className="sm-button primary" disabled={saving}><Plus size={16} /> Add module</button>
                </form>
                {editingModule ? (
                  <form className="sm-form inline" onSubmit={updateModuleDetails}>
                    <label>Module name<input value={editModuleForm.module_name} onChange={(event) => setEditModuleForm({ ...editModuleForm, module_name: event.target.value })} required /></label>
                    <label>Description<input value={editModuleForm.description} onChange={(event) => setEditModuleForm({ ...editModuleForm, description: event.target.value })} /></label>
                    <label>Frontend route<input value={editModuleForm.route} onChange={(event) => setEditModuleForm({ ...editModuleForm, route: event.target.value })} /></label>
                    <label>Additional frontend routes<input value={editModuleForm.frontend_routes} onChange={(event) => setEditModuleForm({ ...editModuleForm, frontend_routes: event.target.value })} /></label>
                    <label>Icon name<input value={editModuleForm.icon} onChange={(event) => setEditModuleForm({ ...editModuleForm, icon: event.target.value })} /></label>
                    <label>API route prefixes<input value={editModuleForm.api_prefixes} onChange={(event) => setEditModuleForm({ ...editModuleForm, api_prefixes: event.target.value })} /></label>
                    <button className="sm-button primary" disabled={saving}><Save size={16} /> Save module</button>
                    <button type="button" className="sm-button" onClick={() => setEditingModule(null)}>Cancel</button>
                  </form>
                ) : null}
                <div className="sm-table-wrap">
                  <table className="sm-table">
                    <thead><tr><th>Module</th><th>Unique key</th><th>Route</th><th>Permissions</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>{snapshot.modules.map((module) => (
                      <tr key={module.id}>
                        <td><strong>{module.module_name}</strong><small>{module.description}</small></td>
                        <td><code>{module.module_key}</code></td><td>{module.route || "—"}</td>
                        <td>{module.permissions?.length || 0}</td>
                        <td><span className={`sm-badge ${module.status ? "on" : "off"}`}>{module.status ? "Active" : "Inactive"}</span></td>
                        <td className="sm-actions">
                          <button type="button" className="sm-icon-button" title="Edit module" onClick={() => {
                            setEditingModule(module);
                            setEditModuleForm({
                              module_name: module.module_name,
                              description: module.description || "",
                              route: module.route || "",
                              frontend_routes: (module.frontend_routes || []).join(", "),
                              icon: module.icon || "",
                              api_prefixes: (module.api_prefixes || []).join(", "),
                            });
                          }}><Edit3 size={16} /></button>
                          <button type="button" className="sm-button small" onClick={() => updateModuleStatus(module)}>{module.status ? "Deactivate" : "Activate"}</button>
                          <button type="button" className="sm-icon-button danger" title="Delete module" onClick={() => removeModule(module)}><Trash2 size={16} /></button>
                        </td>
                      </tr>
                    ))}</tbody>
                  </table>
                </div>
              </section>
            ) : null}

            {activeTab === "Permissions" ? (
              <section className="sm-panel">
                <div className="sm-panel-heading">
                  <div><h2>Role permission matrix</h2><p>Select one role and grant only the module actions it needs.</p>
                    <small>{permissionLastUpdated ? `Last updated ${new Date(permissionLastUpdated).toLocaleString()}` : "No permission changes recorded yet."}</small>
                  </div>
                  <label className="sm-select-label">Role
                    <select value={selectedRole} onChange={(event) => setSelectedRole(event.target.value)}>
                      {snapshot.roles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                    </select>
                  </label>
                </div>
                <div className="sm-table-wrap">
                  <table className="sm-table sm-permission-table">
                    <thead><tr><th>Module</th><th><label><input type="checkbox" checked={permissionModules.length > 0 && permissionModules.every((module) => module.permissions.every((permission) => permission.granted))} onChange={(event) => permissionModules.forEach((module) => toggleModulePermissions(module, event.target.checked))} /> Select all role permissions</label></th><th>Available actions</th></tr></thead>
                    <tbody>{permissionModules.map((module) => (
                      <tr key={module.id}>
                        <td><strong>{module.module_name}</strong><small>{module.module_key}</small></td>
                        <td><input aria-label={`Select all permissions for ${module.module_name}`} type="checkbox" checked={module.permissions.length > 0 && module.permissions.every((permission) => permission.granted)} onChange={(event) => toggleModulePermissions(module, event.target.checked)} /></td>
                        <td className="sm-permissions">{module.permissions.map((permission) => (
                          <label key={permission.id}>
                            <input type="checkbox" checked={Boolean(permission.granted)} onChange={(event) => togglePermission(module.id, permission.id, event.target.checked)} />
                            {permission.permission_key}
                          </label>
                        ))}</td>
                      </tr>
                    ))}</tbody>
                  </table>
                </div>
                <button type="button" className="sm-button primary" disabled={saving} onClick={savePermissions}><Save size={16} /> Save permissions</button>
              </section>
            ) : null}

            {activeTab === "User access" ? (
              <section className="sm-panel">
                <div className="sm-panel-heading"><div><h2>User role assignment</h2><p>Change roles only when the account requires different access.</p></div>
                  <form className="sm-search" onSubmit={(event) => { event.preventDefault(); loadUsers(); }}>
                    <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search user" />
                    <button className="sm-button">Search</button>
                  </form>
                </div>
                <div className="sm-table-wrap">
                  <table className="sm-table">
                    <thead><tr><th>User</th><th>Username</th><th>Role</th><th>Assign role</th></tr></thead>
                    <tbody>{users.map((user) => (
                      <tr key={user.id}>
                        <td>{user.name}<small>{user.email}</small></td><td>{user.username}</td><td>{user.role?.name || "Unassigned"}</td>
                        <td><select value={user.role_id || ""} onChange={(event) => assignRole(user.id, event.target.value)} aria-label={`Role for ${user.name}`}>
                          <option value="" disabled>Select role</option>
                          {snapshot.roles.filter((role) => role.is_active && role.name.toLowerCase() !== "superadmin").map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                        </select></td>
                      </tr>
                    ))}</tbody>
                  </table>
                </div>
              </section>
            ) : null}

            {activeTab === "Audit log" ? (
              <section className="sm-panel">
                <div className="sm-panel-heading"><div><h2>Activity audit log</h2><p>Successful changes and tracked candidate profile updates.</p></div>
                  <button type="button" className="sm-button" onClick={() => {
                    const params = new URLSearchParams(Object.entries(auditFilters).filter(([key, value]) => key !== "page" && value));
                    download(`/system/audit-logs/export${params.size ? `?${params}` : ""}`, "manomilan-audit-logs.csv");
                  }}><Download size={16} /> Export</button>
                </div>
                <form className="sm-form inline sm-audit-filters" onSubmit={applyAuditFilters}>
                  <label>Search user or candidate<input value={auditFilters.q} onChange={(event) => setAuditFilters({ ...auditFilters, q: event.target.value })} /></label>
                  <label>Module<select value={auditFilters.module_id} onChange={(event) => setAuditFilters({ ...auditFilters, module_id: event.target.value })}>
                    <option value="">All modules</option>
                    {snapshot.modules.map((module) => <option key={module.id} value={module.id}>{module.module_name}</option>)}
                  </select></label>
                  <label>Action<select value={auditFilters.action} onChange={(event) => setAuditFilters({ ...auditFilters, action: event.target.value })}>
                    <option value="">All actions</option>
                    {auditActions.map((action) => <option key={action} value={action}>{action}</option>)}
                  </select></label>
                  <label>From<input type="date" value={auditFilters.from} onChange={(event) => setAuditFilters({ ...auditFilters, from: event.target.value })} /></label>
                  <label>To<input type="date" value={auditFilters.to} onChange={(event) => setAuditFilters({ ...auditFilters, to: event.target.value })} /></label>
                  <button className="sm-button">Filter</button>
                </form>
                <div className="sm-table-wrap">
                  <table className="sm-table">
                    <thead><tr><th>Date</th><th>User / IP</th><th>Action</th><th>Module / Entity</th><th>Candidate</th><th>Changes</th></tr></thead>
                    <tbody>{auditLogs.map((log) => (
                      <tr key={log.id}>
                        <td>{new Date(log.created_at).toLocaleString()}</td>
                        <td>{log.user_name_snapshot || "Deleted user"}<small>{log.ip_address || "IP unavailable"}</small></td>
                        <td>{log.action}</td><td>{log.module_name_snapshot || log.entity_type || "System"}<small>{log.description}</small></td>
                        <td>{log.candidate_id || "—"}</td>
                        <td><details><summary>View</summary><pre>{JSON.stringify({ old: log.old_values, new: log.new_values }, null, 2)}</pre></details></td>
                      </tr>
                    ))}</tbody>
                  </table>
                </div>
                {auditMeta?.current_page < auditMeta?.last_page ? <button className="sm-button" type="button" onClick={() => setAuditFilters((filters) => ({ ...filters, page: String(auditMeta.current_page + 1) }))}>More logs</button> : null}
              </section>
            ) : null}
          </>
        ) : null}
      </div>
    </AdminLayout>
  );
}
