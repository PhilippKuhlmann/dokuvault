<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the last run of an installed agent brought: per role ok or not, with
 * the message (POST /api/agent/report). Before, a failing AD run was only
 * visible in the event log of the DC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_installations', function (Blueprint $table) {
            $table->timestamp('last_run_at')->nullable()->after('last_seen_at');
            // {role: {ok: bool, message: string|null, at: ISO-8601}}
            $table->json('last_results')->nullable()->after('last_run_at');
        });
    }

    public function down(): void
    {
        Schema::table('agent_installations', function (Blueprint $table) {
            $table->dropColumn(['last_run_at', 'last_results']);
        });
    }
};
