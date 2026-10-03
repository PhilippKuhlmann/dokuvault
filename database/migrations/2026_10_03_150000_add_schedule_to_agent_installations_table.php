<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an installed agent runs is decided here, not on the machine: the
 * agent asks every few minutes (checkin) and runs when its interval is due
 * or someone pressed "Jetzt melden" on the agent page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_installations', function (Blueprint $table) {
            // Minutes; null = the default (config custom.agent_intervalle).
            $table->unsignedSmallInteger('interval_minutes')->nullable()->after('roles');
            $table->timestamp('run_requested_at')->nullable()->after('interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('agent_installations', function (Blueprint $table) {
            $table->dropColumn(['interval_minutes', 'run_requested_at']);
        });
    }
};
