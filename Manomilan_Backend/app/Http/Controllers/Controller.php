<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use App\Profile;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    const CURRENT_MELAVA_BATCH = 'october_2026';

protected function applyRegistrationTypeScope($query)
{
    if (!Auth::check()) {
        return $query;
    }

    $user = Auth::user();

    // Admins can see all profiles
    $isAdmin = ($user->role_id == 1 || $user->user_type == '00');

    if ($isAdmin) {
        return $query;
    }

    if ($this->isOctober2026Candidate()) {
        // Restrict to the current Melava batch, covering both freshly
        // registered (melava) and re-registered (existing) candidates.
        // Matching on registration_type (instead of the "MM3-" profile_id
        // prefix) keeps re-registered existing candidates searchable even
        // when they retained a legacy profile_id.
        return $query
            ->where('profiles.melava_batch', self::CURRENT_MELAVA_BATCH)
            ->whereIn('profiles.registration_type', ['melava', 'existing']);
    }

    // Hide only Melava profiles from October 2026 batch
    $query->where(function ($q) {
        $q->where('registration_type', '!=', 'melava')
          ->orWhere('melava_batch', '!=', self::CURRENT_MELAVA_BATCH)
          ->orWhereNull('melava_batch');
    });

    return $query;
}

protected function isOctober2026Candidate()
{
    $user = Auth::user();
    if (!$user || $user->user_type != '21' || !$user->profile_id) {
        return false;
    }

    $profile = Profile::find($user->profile_id);

    return $profile
        && $profile->melava_batch === self::CURRENT_MELAVA_BATCH
        && strpos((string) $profile->profile_id, 'MM3-') === 0;
}

protected function canViewProfileByRegistrationType(Profile $profile)
{
    if (!Auth::check()) {
        return true;
    }

    $user = Auth::user();
    if ($user->role_id == 1 || $user->user_type == '00') {
        return true;
    }

    if ($this->isOctober2026Candidate()) {
        return $profile->melava_batch === self::CURRENT_MELAVA_BATCH
            && in_array($profile->registration_type, ['melava', 'existing'], true);
    }

    return !($profile->registration_type === 'melava'
        && $profile->melava_batch === self::CURRENT_MELAVA_BATCH);
}
}
