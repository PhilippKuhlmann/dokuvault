<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the hypervisor agents report about hosts and guests beyond the
 * columns: CPU, memory, kernel, storages of a host; type, VMID, state,
 * cores and memory of a guest. Read only, replaced by every run - like
 * agent_details of the firewalls.
 *
 * Clusters get an agent_identifier: the Proxmox agent creates the cluster
 * from the name in corosync.conf, and every node of it has to find the same
 * entry again.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['servers', 'vms'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->json('agent_details')->nullable()->after('agent_identifier');
                $table->timestamp('agent_reported_at')->nullable()->after('agent_details');
            });
        }

        Schema::table('clusters', function (Blueprint $table) {
            $table->string('agent_identifier')->nullable()->after('site_id');

            $table->index(['customer_id', 'agent_identifier']);
        });
    }

    public function down(): void
    {
        Schema::table('clusters', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'agent_identifier']);
            $table->dropColumn('agent_identifier');
        });

        foreach (['servers', 'vms'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->dropColumn(['agent_details', 'agent_reported_at']);
            });
        }
    }
};
