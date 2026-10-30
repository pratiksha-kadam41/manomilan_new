<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateNotification extends Model
{
    protected $table = 'candidate_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function pushTo(User $user, string $type, string $title, string $message = '', array $data = []): ?self
    {
        try {
            return self::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
