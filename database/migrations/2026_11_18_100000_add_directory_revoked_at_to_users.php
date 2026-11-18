<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the hourly directory sync ended a directory account's access - it
 * was disabled or deleted in AD, or left every mapped group. Its sessions
 * and app tokens were cut then; the mark keeps the next runs from doing it
 * (and logging it) again every hour. A successful sign-in clears it: back
 * in the group means back in DokuVault.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('directory_revoked_at')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('directory_revoked_at');
        });
    }
};
