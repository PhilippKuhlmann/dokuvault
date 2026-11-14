<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The network scan of the Windows agent: printers and UPS found by SNMP.
 *
 * - printers, ups: agent_identifier (the serial number, or the MAC address
 *   when the device reports none) ties a device to what the scan finds;
 *   firmware is a fact of the documentation like the model - printers and
 *   UPS network cards are reachable in the network and have security
 *   advisories of their own.
 * - agent_installations: which documented networks to scan and with which
 *   SNMP communities (encrypted, like every password), plus the timing - at
 *   most once a day, or when "Jetzt scannen" was pressed.
 * - network_scan_findings: what the scan found and nobody decided on yet,
 *   and what was ignored. A taken-over device leaves this table: from then
 *   on it carries the agent_identifier and is updated directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['printers', 'ups'] as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->string('agent_identifier')->nullable()->after('site_id');
                $table->string('firmware')->nullable()->after('serialNumber');

                $table->index(['customer_id', 'agent_identifier']);
            });
        }

        Schema::table('agent_installations', function (Blueprint $table) {
            $table->json('scan_network_ids')->nullable();
            $table->text('scan_communities')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamp('scan_requested_at')->nullable();
        });

        Schema::create('network_scan_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_installation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('network_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('identifier');
            $table->string('status', 20)->default('new');
            $table->string('name')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->string('firmware')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'type', 'identifier']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_scan_findings');

        Schema::table('agent_installations', function (Blueprint $table) {
            $table->dropColumn(['scan_network_ids', 'scan_communities', 'last_scan_at', 'scan_requested_at']);
        });

        foreach (['printers', 'ups'] as $tabelle) {
            // MySQL dropped the index of the customer_id foreign key once the
            // combined index above could serve it - and refuses to drop that
            // one now ("needed in a foreign key constraint"). A plain index
            // first, then the combined one can go.
            Schema::table($tabelle, function (Blueprint $table) use ($tabelle) {
                if (! collect(Schema::getIndexes($tabelle))->contains(fn ($i) => $i['columns'] === ['customer_id'])) {
                    $table->index('customer_id');
                }
            });

            Schema::table($tabelle, function (Blueprint $table) {
                $table->dropIndex(['customer_id', 'agent_identifier']);
                $table->dropColumn(['agent_identifier', 'firmware']);
            });
        }
    }
};
