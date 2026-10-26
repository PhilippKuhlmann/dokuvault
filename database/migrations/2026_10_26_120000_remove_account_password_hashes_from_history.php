<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The history kept the bcrypt hash of every changed account password. It
 * cannot bring the old password back, but whoever may read the activity log
 * could crack it offline. New ones are no longer archived (TracksChanges);
 * this removes the ones already there. That the password was changed stays
 * in the activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('password_histories')->where('subject_type', 'App\\Models\\User')->delete();
    }

    public function down(): void
    {
        // Deleted hashes do not come back - and are not meant to.
    }
};
