<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of what decides whether a domain's mail arrives: DKIM keys and
 * the reverse DNS of its mail servers.
 *
 * DKIM selectors can't be listed over DNS - only asked for by name. The
 * check tries the common ones; dkim_selectors holds the ones someone knows
 * and the check could not guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // Per selector found: key type, length, withdrawn or not.
            $table->json('dkim')->nullable()->after('dmarc');
            $table->string('dkim_selectors')->nullable()->after('dkim');
            $table->json('ptr')->nullable()->after('dkim_selectors');
            // What the registry names as registrar - often the wholesaler
            // (Key-Systems) behind the provider the customer actually deals
            // with (EWE). Kept apart from "registrar", which is typed in.
            $table->string('registry_registrar')->nullable()->after('registrar');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['dkim', 'dkim_selectors', 'ptr', 'registry_registrar']);
        });
    }
};
