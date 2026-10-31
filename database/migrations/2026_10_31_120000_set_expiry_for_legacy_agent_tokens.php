<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agent tokens created before the mandatory expiry (2026_09_21) have
     * expires_at = null and would never expire. New tokens get a deadline in
     * the controller, so these were the last ones without.
     *
     * They get a year from here, not an immediate end: an agent that is
     * running should keep running, and a year is the same span the agent page
     * suggests for a new token (Setting::AGENT_TOKEN_TAGE). The date shows in
     * the token list from now on, so the deadline is visible long before it is
     * reached, and "Erneuern" moves it.
     */
    public function up(): void
    {
        DB::table('agent_tokens')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(365)]);
    }

    /**
     * Nothing to undo: which rows were null is not recorded anywhere, and
     * putting every token back to "no expiry" would be worse than leaving the
     * date in place. A single token is set back through the agent page.
     */
    public function down(): void {}
};
