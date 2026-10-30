<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class ApiToken
{
    public static function create(int $userId, int $lifetimeSeconds = 28800): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $userId,
            'expires_at' => time() + $lifetimeSeconds,
        ], JSON_THROW_ON_ERROR));
    }

    public static function userId(string $token): ?int
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            return null;
        }

        if (! is_array($payload)
            || ! isset($payload['user_id'], $payload['expires_at'])
            || (int) $payload['expires_at'] < time()) {
            return null;
        }

        return (int) $payload['user_id'];
    }
}
