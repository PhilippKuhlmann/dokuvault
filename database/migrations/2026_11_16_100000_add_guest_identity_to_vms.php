<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What ties a VM seen from its hypervisor to the same machine seen from
 * inside.
 *
 * - smbios_uuid: the BIOS UUID the hypervisor gives the VM (Proxmox:
 *   smbios1). Windows reads the same value inside the guest
 *   (Win32_ComputerSystemProduct). The Windows agent's report then lands on
 *   this VM instead of creating a server next to it.
 * - hostname: the name the operating system inside reports. It often
 *   differs from the VM's name in the hypervisor ("test" vs.
 *   "WIN-H4KK383P230") - the AD agent names domain controllers by it.
 *
 * A plain index, as on agent_identifier elsewhere: a combined one with
 * customer_id would take over the foreign key's index on MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->string('smbios_uuid', 36)->nullable()->after('agent_identifier')->index();
            $table->string('hostname')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropIndex(['smbios_uuid']);
            $table->dropColumn(['smbios_uuid', 'hostname']);
        });
    }
};
