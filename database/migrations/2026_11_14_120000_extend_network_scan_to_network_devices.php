<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The network scan finds switches, access points, NAS and cameras too.
 *
 * - All four get firmware, as printers and UPS did: a device reachable in
 *   the network with security advisories of its own.
 * - NAS and cameras get agent_identifier, which ties them to the scan.
 *   Switches and access points have it since the UniFi agent - the scan
 *   uses the same column, with its own values (serial, else "mac:...").
 *
 * A plain index on agent_identifier, as in the UniFi migration: a combined
 * one with customer_id would take over the foreign key's index on MySQL and
 * block the rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['network_switches', 'accesspoints', 'nas', 'cameras'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->string('firmware')->nullable()->after('serialNumber');
            });
        }

        foreach (['nas', 'cameras'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->string('agent_identifier')->nullable()->after('site_id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['nas', 'cameras'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->dropIndex(['agent_identifier']);
                $table->dropColumn('agent_identifier');
            });
        }

        foreach (['network_switches', 'accesspoints', 'nas', 'cameras'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->dropColumn('firmware');
            });
        }
    }
};
