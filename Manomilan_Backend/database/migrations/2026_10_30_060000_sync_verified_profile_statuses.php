<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('profiles')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('document_verifications')
                    ->whereColumn('document_verifications.profile_id', 'profiles.id')
                    ->whereRaw('LOWER(TRIM(identificationProof_status)) = ?', ['verified'])
                    ->whereRaw('LOWER(TRIM(birthDateProof_status)) = ?', ['verified']);
            })
            ->update(['profileVerificationStatus' => 'Completed']);

        DB::table('profiles')
            ->where('profileVerificationStatus', 'Completed')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('document_verifications')
                    ->whereColumn('document_verifications.profile_id', 'profiles.id')
                    ->whereRaw('LOWER(TRIM(identificationProof_status)) = ?', ['verified'])
                    ->whereRaw('LOWER(TRIM(birthDateProof_status)) = ?', ['verified']);
            })
            ->update(['profileVerificationStatus' => 'Pending']);
    }

    public function down(): void
    {
        // Verification status is repaired data and should not be reverted.
    }
};
