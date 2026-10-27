<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One token per agent, and a token that can be locked.
 *
 * machine_id: the machine the token was first checked in from. Another
 * machine with the same token is refused - so locking a token stops exactly
 * one agent. locked_at: set by "lock" in the agent list; a locked token gets
 * a 401 until it is unlocked, without reinstalling anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_tokens', function (Blueprint $table) {
            $table->string('machine_id')->nullable()->after('site_id');
            $table->timestamp('locked_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('agent_tokens', function (Blueprint $table) {
            $table->dropColumn(['machine_id', 'locked_at']);
        });
    }
};
