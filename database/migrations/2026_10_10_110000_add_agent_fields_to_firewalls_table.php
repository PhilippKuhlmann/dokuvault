<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firewalls reported by the firewall agent (OPNsense, Securepoint).
 *
 * agent_details holds what has no column of its own and is only read, never
 * edited here: interfaces, VPNs, port forwards, gateways. The next run
 * replaces it as a whole - it is the firewall's state, not documentation
 * someone writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            $table->string('agent_identifier')->nullable()->after('site_id');
            $table->json('agent_details')->nullable()->after('notes');
            $table->timestamp('agent_reported_at')->nullable()->after('agent_details');

            $table->index(['customer_id', 'agent_identifier']);
        });
    }

    public function down(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'agent_identifier']);
            $table->dropColumn(['agent_identifier', 'agent_details', 'agent_reported_at']);
        });
    }
};
