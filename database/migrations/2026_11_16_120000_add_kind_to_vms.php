<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a guest is a virtual machine or a container (LXC) - as a field of
 * its own, set by hand or by the agent.
 *
 * Before, only the Proxmox agent's report said so (agent_details.type). A
 * guest entered by hand had no kind at all and stood on the server map as
 * a mere "Gast".
 *
 * Existing guests: what the agent reported as lxc becomes lxc, everything
 * else a VM - the far more common kind. LIKE instead of a JSON query: the
 * tests run on SQLite, production on MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->string('kind', 10)->default('vm')->after('name');
        });

        DB::table('vms')
            ->where(fn ($q) => $q->where('agent_details', 'like', '%"type":"lxc"%')
                ->orWhere('agent_details', 'like', '%"type": "lxc"%'))
            ->update(['kind' => 'lxc']);
    }

    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
