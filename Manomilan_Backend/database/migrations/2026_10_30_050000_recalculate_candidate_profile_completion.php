<?php

use App\Profile;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Profile::with('user.preference')->chunkById(100, function ($profiles) {
            foreach ($profiles as $profile) {
                $profile->calculateCompletionPercentage();
            }
        });
    }

    public function down(): void
    {
        // Recalculation is not reversible.
    }
};
