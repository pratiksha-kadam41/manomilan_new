<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AuditLogger;
use App\Module;
use App\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemManagementController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index()
    {
        $roles = Role::query()
            ->withCount('users')
            ->withCount('modulePermissions')
            ->orderBy('name')
            ->get();
        $roles->each(fn (Role $role) => $role->setAttribute('is_protected', $role->isProtectedSystemRole()));
        $modules = Module::query()
            ->with('permissions:id,module_id,permission_key')
            ->orderBy('sort_order')
            ->orderBy('module_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles,
                'modules' => $modules,
                'statistics' => [
                    'roles' => Role::count(),
                    'modules' => Module::count(),
                    'active_users' => User::where('status', 'active')->count(),
                    'assigned_permissions' => DB::table('role_module_permissions')->count(),
                    'audit_activities' => ActivityLog::count(),
                ],
            ],
        ]);
    }

    public function myPermissions(Request $request)
    {
        $user = $request->user();
        $permissions = $user->isSuperAdmin()
            ? DB::table('module_permissions')
                ->join('modules', 'modules.id', '=', 'module_permissions.module_id')
                ->where('modules.status', true)
                ->select('modules.id as module_id', 'modules.module_key', 'modules.module_name', 'modules.route', 'modules.frontend_routes', 'modules.icon', 'modules.sort_order', 'module_permissions.permission_key')
                ->get()
            : DB::table('role_module_permissions')
                ->join('module_permissions', 'module_permissions.id', '=', 'role_module_permissions.module_permission_id')
                ->join('modules', 'modules.id', '=', 'module_permissions.module_id')
                ->join('roles', 'roles.id', '=', 'role_module_permissions.role_id')
                ->where('roles.id', $user->role_id)
                ->where('roles.is_active', true)
                ->where('modules.status', true)
                ->select('modules.id as module_id', 'modules.module_key', 'modules.module_name', 'modules.route', 'modules.frontend_routes', 'modules.icon', 'modules.sort_order', 'module_permissions.permission_key')
                ->get();

        $data = $permissions
            ->groupBy('module_id')
            ->map(function ($items) {
                $module = $items->first();
                $grantedPermissions = $items->pluck('permission_key')->unique()->values();
                if ($grantedPermissions->contains('manage')) {
                    $grantedPermissions = DB::table('module_permissions')
                        ->where('module_id', $module->module_id)
                        ->orderBy('permission_key')
                        ->pluck('permission_key')
                        ->unique()
                        ->values();
                }

                return [
                    'id' => $module->module_id,
                    'key' => $module->module_key,
                    'name' => $module->module_name,
                    'route' => $module->route,
                    'routes' => json_decode((string) $module->frontend_routes, true) ?: array_filter([$module->route]),
                    'icon' => $module->icon,
                    'sort_order' => $module->sort_order,
                    'requires_super_admin' => $module->module_key === 'system_configuration',
                    'permissions' => $grantedPermissions,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $user->role?->name,
                'is_super_admin' => $user->isSuperAdmin(),
                'modules' => $data,
            ],
        ]);
    }

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'user_type' => ['required', 'string', 'max:255', 'unique:roles,user_type'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($this->isSuperAdminIdentity($data['name'], $data['user_type'])) {
            return response()->json(['success' => false, 'message' => 'The Super Admin identity is reserved.'], 422);
        }

        $role = Role::create($data + ['is_active' => true]);
        $this->auditLogger->log($request->user(), 'role.created', 'system_configuration', 'Role', $role->id, null, [], $role->only(['name', 'user_type', 'is_active']));

        return response()->json(['success' => true, 'data' => $role], 201);
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->protectSystemRole($role);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'user_type' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles', 'user_type')->ignore($role->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($this->isSuperAdminIdentity(
            $data['name'] ?? $role->name,
            $data['user_type'] ?? $role->user_type
        )) {
            return response()->json(['success' => false, 'message' => 'The Super Admin identity is reserved.'], 422);
        }

        $old = $role->only(array_keys($data));
        $role->fill($data)->save();
        $this->auditLogger->log($request->user(), 'role.updated', 'system_configuration', 'Role', $role->id, null, $old, $role->only(array_keys($data)));

        return response()->json(['success' => true, 'data' => $role]);
    }

    public function deleteRole(Request $request, Role $role)
    {
        $this->protectSystemRole($role);
        if ($role->users()->exists()) {
            return response()->json(['success' => false, 'message' => 'This role still has assigned users. Reassign them before deleting it.'], 409);
        }

        $old = $role->only(['id', 'name', 'user_type']);
        $role->delete();
        $this->auditLogger->log($request->user(), 'role.deleted', 'system_configuration', 'Role', $old['id'], null, $old, []);

        return response()->json(['success' => true]);
    }

    public function showRolePermissions(Role $role)
    {
        $grants = DB::table('role_module_permissions')
            ->where('role_id', $role->id)
            ->get(['module_permission_id', 'updated_at'])
            ->keyBy('module_permission_id');
        $modules = Module::query()
            ->where('status', true)
            ->with('permissions')
            ->orderBy('sort_order')
            ->orderBy('module_name')
            ->get();
        $modules->each(function (Module $module) use ($grants) {
            $module->permissions->each(function ($permission) use ($grants) {
                $grant = $grants->get($permission->id);
                $permission->setAttribute('granted', $grant !== null);
                $permission->setAttribute('granted_at', $grant?->updated_at);
            });
        });
        $lastUpdated = ActivityLog::query()
            ->where('action', 'role.permissions_updated')
            ->where('entity_type', 'Role')
            ->where('entity_id', (string) $role->id)
            ->latest()
            ->value('created_at');

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'modules' => $modules,
                'last_updated_at' => $lastUpdated,
            ],
        ]);
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        $this->protectSystemRole($role);
        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:module_permissions,id'],
        ]);
        $permissionIds = DB::table('module_permissions')
            ->whereIn('id', $validated['permissions'])
            ->pluck('id')
            ->all();
        if (count($permissionIds) !== count($validated['permissions'])) {
            return response()->json(['success' => false, 'message' => 'One or more permissions are unavailable.'], 422);
        }

        $oldIds = $role->modulePermissions()->pluck('module_permissions.id')->all();
        DB::transaction(function () use ($role, $permissionIds, $oldIds, $request) {
            $syncValues = collect($permissionIds)
                ->mapWithKeys(fn ($id) => [$id => ['granted_by' => $request->user()->id]])
                ->all();
            $role->modulePermissions()->sync($syncValues);
            $this->auditLogger->log(
                $request->user(),
                'role.permissions_updated',
                'system_configuration',
                'Role',
                $role->id,
                null,
                ['permission_ids' => $oldIds],
                ['permission_ids' => $permissionIds]
            );
        });

        return response()->json(['success' => true]);
    }

    public function storeModule(Request $request)
    {
        $data = $request->validate([
            'module_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'route' => ['nullable', 'string', 'max:255'],
            'frontend_routes' => ['nullable', 'array'],
            'frontend_routes.*' => ['string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'api_prefixes' => ['nullable', 'array'],
            'api_prefixes.*' => ['string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        if (Module::whereRaw('LOWER(module_name) = ?', [mb_strtolower($data['module_name'])])->exists()) {
            return response()->json(['success' => false, 'message' => 'A module with this name already exists.'], 422);
        }

        $applicationId = DB::table('applications')->orderBy('id')->value('id');
        if (! $applicationId) {
            return response()->json(['success' => false, 'message' => 'Create an application record before adding modules.'], 422);
        }

        $baseKey = Str::limit(Str::slug($data['module_name'], '_'), 110, '') ?: 'module';
        $moduleKey = $baseKey;
        $suffix = 2;
        while (Module::where('module_key', $moduleKey)->exists()) {
            $moduleKey = Str::limit($baseKey, 105, '') . '_' . $suffix++;
        }

        $module = DB::transaction(function () use ($data, $applicationId, $moduleKey) {
            $module = Module::create($data + [
                'application_id' => $applicationId,
                'module_key' => $moduleKey,
                'status' => true,
            ]);
            $permissionKeys = DB::table('module_permissions')->distinct()->pluck('permission_key');
            foreach ($permissionKeys as $permissionKey) {
                $module->permissions()->create(['permission_key' => $permissionKey]);
            }

            return $module->load('permissions');
        });
        $this->auditLogger->log($request->user(), 'module.created', 'system_configuration', 'Module', $module->id, null, [], $module->only(['module_key', 'module_name', 'description']));

        return response()->json(['success' => true, 'data' => $module], 201);
    }

    public function updateModule(Request $request, Module $module)
    {
        $data = $request->validate([
            'module_name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'route' => ['nullable', 'string', 'max:255'],
            'frontend_routes' => ['nullable', 'array'],
            'frontend_routes.*' => ['string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'api_prefixes' => ['nullable', 'array'],
            'api_prefixes.*' => ['string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['module_name']) && Module::whereRaw('LOWER(module_name) = ?', [mb_strtolower($data['module_name'])])->where('id', '!=', $module->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'A module with this name already exists.'], 422);
        }

        $old = $module->only(array_keys($data));
        $module->fill($data)->save();
        $this->auditLogger->log($request->user(), 'module.updated', 'system_configuration', 'Module', $module->id, null, $old, $module->only(array_keys($data)));

        return response()->json(['success' => true, 'data' => $module->fresh()]);
    }

    public function deleteModule(Request $request, Module $module)
    {
        if (DB::table('role_module_permissions')
            ->join('module_permissions', 'module_permissions.id', '=', 'role_module_permissions.module_permission_id')
            ->where('module_permissions.module_id', $module->id)
            ->exists()) {
            return response()->json(['success' => false, 'message' => 'Remove this module’s role grants before deleting it.'], 409);
        }

        $old = $module->only(['id', 'module_key', 'module_name']);
        $module->delete();
        $this->auditLogger->log($request->user(), 'module.deleted', 'system_configuration', 'Module', $old['id'], null, $old, []);

        return response()->json(['success' => true]);
    }

    public function users(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $users = User::query()
            ->with('role:id,name')
            ->when($filters['q'] ?? '', function ($query, $term) {
                $query->where(function ($search) use ($term) {
                    $search->where('name', 'like', '%' . $term . '%')
                        ->orWhere('username', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%');
                });
            })
            ->orderBy('name')
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function assignUserRole(Request $request, User $user)
    {
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'You cannot change your own role.'], 403);
        }
        $data = $request->validate(['role_id' => ['required', 'integer', 'exists:roles,id']]);
        $role = Role::findOrFail($data['role_id']);
        if (! $role->is_active) {
            return response()->json(['success' => false, 'message' => 'An inactive role cannot be assigned.'], 422);
        }
        if ($role->isProtectedSystemRole()) {
            return response()->json(['success' => false, 'message' => 'Super Admin cannot be assigned to another account.'], 403);
        }

        $oldRole = $user->role?->name;
        $user->role()->associate($role);
        $user->user_type = $role->user_type;
        $user->save();
        $this->auditLogger->log($request->user(), 'user.role_changed', 'authentication_security', 'User', $user->id, $user->profile_id, ['role' => $oldRole], ['role' => $role->name]);

        return response()->json(['success' => true, 'data' => $user->load('role')]);
    }

    public function auditLogs(Request $request)
    {
        $filters = $request->validate($this->auditFilterRules());
        $logs = $this->filteredAuditQuery($filters)
            ->with(['user:id,name', 'module:id,module_name'])
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json([
            'success' => true,
            'data' => $logs,
            'filters' => [
                'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            ],
        ]);
    }

    public function exportAuditLogs(Request $request): StreamedResponse
    {
        $filters = $request->validate($this->auditFilterRules());
        $logs = $this->filteredAuditQuery($filters)
            ->latest()
            ->limit(10000)
            ->get(['created_at', 'user_name_snapshot', 'action', 'module_name_snapshot', 'entity_type', 'entity_id', 'candidate_id', 'description', 'ip_address']);

        return response()->streamDownload(function () use ($logs) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'User', 'Action', 'Module', 'Entity', 'Entity ID', 'Candidate ID', 'Description', 'IP address']);
            foreach ($logs as $log) {
                fputcsv($output, $log->toArray());
            }
            fclose($output);
        }, 'manomilan-audit-logs.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function auditFilterRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:100'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function filteredAuditQuery(array $filters)
    {
        return ActivityLog::query()
            ->with(['user:id,name', 'module:id,module_name'])
            ->when($filters['q'] ?? '', function ($query, $term) {
                $query->where(function ($search) use ($term) {
                    $search->where('user_name_snapshot', 'like', '%' . $term . '%')
                        ->orWhere('candidate_id', 'like', '%' . $term . '%')
                        ->orWhere('entity_id', 'like', '%' . $term . '%')
                        ->orWhere('description', 'like', '%' . $term . '%');
                });
            })
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['module_id'] ?? null, fn ($query, $id) => $query->where('module_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function protectSystemRole(Role $role): void
    {
        abort_if($role->isProtectedSystemRole(), 403, 'The Super Admin role is protected.');
    }

    private function isSuperAdminIdentity(string $name, string $userType): bool
    {
        return preg_replace('/[^a-z]/', '', strtolower($name)) === 'superadmin'
            || $userType === '00';
    }
}
