<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Installations set up before the "setup done" mark existed: one with an
 * administrator is set up. Without the mark a SETUP_TOKEN in the .env would
 * reopen /setup as soon as no role-1 account was left.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('users')->where('role_id', 1)->exists() || DB::table('settings')->where('key', 'setup_done')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'setup_done',
            'value' => now()->toIso8601String(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('settings.alle');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'setup_done')->delete();
    }
};
