<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicateUsernames = DB::table('users')
                ->select('username')
                ->groupBy('username')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('username');

            foreach ($duplicateUsernames as $username) {
                $duplicates = DB::table('users')
                    ->where('username', $username)
                    ->orderBy('id')
                    ->get(['id'])
                    ->skip(1);

                foreach ($duplicates as $user) {
                    $suffix = '_'.$user->id;
                    $candidate = substr((string) $username, 0, 255 - strlen($suffix)).$suffix;
                    $counter = 1;
                    while (DB::table('users')->where('username', $candidate)->exists()) {
                        $suffix = '_'.$user->id.'_'.$counter++;
                        $candidate = substr((string) $username, 0, 255 - strlen($suffix)).$suffix;
                    }

                    DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_username_index');
            $table->unique('username', 'users_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_username_unique');
            $table->index('username', 'users_username_index');
        });
    }
};
