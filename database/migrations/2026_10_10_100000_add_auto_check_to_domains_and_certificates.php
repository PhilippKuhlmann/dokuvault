<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domains and certificates are checked by DokuVault itself (domains:check):
 * expiry and DNS of a domain, the certificate a host actually serves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('mx')->nullable()->after('nameserver2');
            $table->text('spf')->nullable()->after('mx');
            $table->text('dmarc')->nullable()->after('spf');
            $table->boolean('auto_check')->default(true)->after('dmarc');
            $table->timestamp('checked_at')->nullable()->after('auto_check');
            $table->string('check_error')->nullable()->after('checked_at');
        });

        Schema::table('certificates', function (Blueprint $table) {
            // Empty means common_name - a wildcard needs a concrete host.
            $table->string('check_host')->nullable()->after('expiry_date');
            $table->unsignedInteger('check_port')->nullable()->after('check_host');
            $table->boolean('auto_check')->default(true)->after('check_port');
            $table->timestamp('checked_at')->nullable()->after('auto_check');
            $table->string('check_error')->nullable()->after('checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['mx', 'spf', 'dmarc', 'auto_check', 'checked_at', 'check_error']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['check_host', 'check_port', 'auto_check', 'checked_at', 'check_error']);
        });
    }
};
