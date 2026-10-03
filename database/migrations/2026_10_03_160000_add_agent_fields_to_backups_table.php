<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backups reported by the agents (Veeam, Windows Server-Sicherung, Proxmox
 * vzdump): found again by agent_identifier, with the outcome of the last
 * run - "Letzter Erfolg" alone does not say that last night failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('agent_identifier')->nullable()->index()->after('customer_id');
            // ok | warning | failed - null: entered by hand, nobody knows.
            $table->string('last_status')->nullable()->after('last_success');
            $table->timestamp('last_run_at')->nullable()->after('last_status');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn(['agent_identifier', 'last_status', 'last_run_at']);
        });
    }
};
