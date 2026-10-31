<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SENSITIVE_FIELD = '/password|token|secret|proof|document|photo|screenshot/i';

    public function log(
        ?User $user,
        string $action,
        ?string $moduleKey = null,
        ?string $entityType = null,
        int|string|null $entityId = null,
        int|string|null $candidateId = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        ?Request $request = null
    ): ActivityLog {
        $request ??= app()->runningInConsole() ? null : request();
        $module = $moduleKey
            ? \App\Module::query()->where('module_key', $moduleKey)->first()
            : $this->moduleForRequest($request);

        return ActivityLog::create([
            'user_id' => $user?->id,
            'user_name_snapshot' => $user?->name,
            'action' => $action,
            'module_id' => $module?->id,
            'module_name_snapshot' => $module?->module_name,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'candidate_id' => $candidateId === null ? null : (string) $candidateId,
            'old_values' => $this->safeValues($oldValues),
            'new_values' => $this->safeValues($newValues),
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_method' => $request?->method(),
            'request_url' => $request?->path(),
        ]);
    }

    public function logModelChange(
        Model $model,
        string $action,
        array $oldValues,
        array $newValues,
        ?int $candidateId = null
    ): ActivityLog {
        $user = auth()->user();
        $module = \App\Module::query()
            ->whereIn('module_key', ['user_side_profile', 'profile_management'])
            ->orderByRaw("CASE WHEN module_key = 'user_side_profile' THEN 0 ELSE 1 END")
            ->first();

        return $this->log(
            $user instanceof User ? $user : null,
            $action,
            $module?->module_key,
            class_basename($model),
            $model->getKey(),
            $candidateId,
            $oldValues,
            $newValues,
            $action . ' ' . class_basename($model)
        );
    }

    private function safeValues(array $values): array
    {
        $filtered = [];
        foreach ($values as $key => $value) {
            if (preg_match(self::SENSITIVE_FIELD, (string) $key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function moduleForRequest(?Request $request): ?\App\Module
    {
        if (! $request) {
            return null;
        }

        $path = preg_replace('#^api/#', '', trim($request->path(), '/'));
        $selected = null;
        $selectedLength = -1;
        foreach (\App\Module::query()->whereNotNull('api_prefixes')->get() as $module) {
            $prefixes = $module->api_prefixes;
            if (! is_array($prefixes)) {
                continue;
            }
            foreach ($prefixes as $prefix) {
                $pattern = trim(preg_replace('/\{[^}]+\}/', '*', trim((string) $prefix, '/')), '/');
                if ($pattern !== '' && Str::is($pattern, $path) && strlen($pattern) > $selectedLength) {
                    $selected = $module;
                    $selectedLength = strlen($pattern);
                }
            }
        }

        return $selected;
    }
}
