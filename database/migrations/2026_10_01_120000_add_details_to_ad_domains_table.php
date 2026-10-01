<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a technician needs to know about a domain besides its name.
 *
 * Where a service runs (domain controllers, Entra Connect, CA) is a link to a
 * server or VM in ad_domain_hosts, not text: renaming the machine then
 * renames it here too, and a DC can be either kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_domains', function (Blueprint $table) {
            $table->string('functional_level')->nullable()->after('netbios');
            $table->string('fsmo_holder')->nullable()->after('functional_level');
            $table->string('upn_suffixes')->nullable()->after('fsmo_holder');
            $table->string('dns_forwarders')->nullable()->after('upn_suffixes');
            $table->string('dhcp_server')->nullable()->after('dns_forwarders');
            $table->boolean('entra_connect')->nullable()->after('dhcp_server');
            $table->text('notes')->nullable()->after('entra_connect');
        });

        Schema::create('ad_domain_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_domain_id')->constrained('ad_domains')->cascadeOnDelete();
            // dc, entra_connect, ca - see ADDomainHost::ROLES.
            $table->string('role', 32);
            $table->morphs('host');
            $table->timestamps();

            $table->unique(['ad_domain_id', 'role', 'host_type', 'host_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_domain_hosts');

        Schema::table('ad_domains', function (Blueprint $table) {
            $table->dropColumn([
                'functional_level', 'fsmo_holder', 'upn_suffixes',
                'dns_forwarders', 'dhcp_server', 'entra_connect', 'notes',
            ]);
        });
    }
};
