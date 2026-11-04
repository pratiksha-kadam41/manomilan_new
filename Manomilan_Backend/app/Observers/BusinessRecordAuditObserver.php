<?php

namespace App\Observers;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use WeakMap;

class BusinessRecordAuditObserver
{
    private static ?WeakMap $changes = null;

    public function __construct(private readonly AuditLogger $auditLogger)
    {
        self::$changes ??= new WeakMap();
    }

    public function creating(Model $model): void
    {
        $actor = $this->actor();
        $this->setActor($model, 'created_by_user_id', $actor);
        $this->setActor($model, 'updated_by_user_id', $actor);
        self::$changes[$model] = ['old' => [], 'new' => $this->safeAttributes($model->getAttributes())];
    }

    public function updating(Model $model): void
    {
        $this->setActor($model, 'updated_by_user_id', $this->actor());

        $old = [];
        $new = [];
        foreach ($model->getDirty() as $key => $value) {
            $old[$key] = $model->getOriginal($key);
            $new[$key] = $value;
        }

        self::$changes[$model] = [
            'old' => $this->safeAttributes($old),
            'new' => $this->safeAttributes($new),
        ];
    }

    public function created(Model $model): void
    {
        $this->write($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->write(
            $model,
            'deleted',
            $this->safeAttributes($model->getOriginal()),
            []
        );
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function setActor(Model $model, string $column, ?User $actor): void
    {
        if ($actor && Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column)) {
            $model->setAttribute($column, $actor->getKey());
        }
    }

    private function write(Model $model, string $action, ?array $old = null, ?array $new = null): void
    {
        $change = self::$changes[$model] ?? ['old' => [], 'new' => []];
        unset(self::$changes[$model]);

        $recordName = class_basename($model);
        $isProfile = $recordName === 'Profile';
        $candidateId = $isProfile ? $model->getKey() : $model->getAttribute('profile_id');

        $this->auditLogger->log(
            $this->actor(),
            $isProfile ? 'candidate.'.$action : 'record.'.$action,
            null,
            $recordName,
            $model->getKey(),
            is_scalar($candidateId) ? $candidateId : null,
            $old ?? $change['old'],
            $new ?? $change['new'],
            ucfirst($action).' '.$recordName
        );
    }

    private function safeAttributes(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn ($value, $key) => ! in_array($key, [
                'created_at',
                'updated_at',
                'created_by_user_id',
                'updated_by_user_id',
            ], true)
                && ! preg_match('/password|token|secret|proof|document|photo|screenshot/i', (string) $key)
                && (is_scalar($value) || $value === null),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
