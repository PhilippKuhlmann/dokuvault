<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The identifier the agent inside a VM knows its machine by (Windows: the
 * MachineGuid) - next to agent_identifier, which belongs to the hypervisor
 * agent ("srv-pve02/qemu/101").
 *
 * Without it a server turned into a VM (by hand or by the BIOS UUID) came
 * back as a server on the agent's next run: it looks for its MachineGuid
 * and found nothing left that carried it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->string('guest_identifier')->nullable()->after('smbios_uuid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropIndex(['guest_identifier']);
            $table->dropColumn('guest_identifier');
        });
    }
};
