<?php

namespace App\Observers;

use App\Profile;
use App\Support\AuditLogger;
use WeakMap;

class ProfileAuditObserver
{
    private static ?WeakMap $beforeUpdate = null;

    public function __construct(private readonly AuditLogger $auditLogger)
    {
        self::$beforeUpdate ??= new WeakMap();
    }

    public function creating(Profile $profile): void
    {
        self::$beforeUpdate[$profile] = ['old' => [], 'new' => $this->safeAttributes($profile->getAttributes())];
    }

    public function updating(Profile $profile): void
    {
        $dirty = $profile->getDirty();
        $old = [];
        $new = [];
        foreach ($dirty as $key => $value) {
            $old[$key] = $profile->getOriginal($key);
            $new[$key] = $value;
        }
        self::$beforeUpdate[$profile] = ['old' => $old, 'new' => $new];
    }

    public function created(Profile $profile): void
    {
        $this->write($profile, 'candidate.created');
    }

    public function updated(Profile $profile): void
    {
        $this->write($profile, 'candidate.updated');
    }

    public function deleted(Profile $profile): void
    {
        $this->auditLogger->logModelChange(
            $profile,
            'candidate.deleted',
            $this->safeAttributes($profile->getOriginal()),
            [],
            (int) $profile->getKey()
        );
    }

    private function write(Profile $profile, string $action): void
    {
        $change = self::$beforeUpdate[$profile] ?? ['old' => [], 'new' => []];
        unset(self::$beforeUpdate[$profile]);

        $this->auditLogger->logModelChange(
            $profile,
            $action,
            $this->safeAttributes($change['old']),
            $this->safeAttributes($change['new']),
            (int) $profile->getKey()
        );
    }

    private function safeAttributes(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn ($value, $key) => ! preg_match('/password|token|secret|proof|document|photo|screenshot/i', (string) $key)
                && (is_scalar($value) || $value === null),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
