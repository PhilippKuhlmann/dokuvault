<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPU, cores and memory by hand - for a server or VM no agent reports
 * (a storage box, a vServer at a provider, a host without agent). The
 * server map showed nothing there. What an agent reports
 * (agent_details) stays first; these fill the gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('cpu')->nullable()->after('model');
            $table->unsignedSmallInteger('cores')->nullable()->after('cpu');
            $table->decimal('memory_gb', 8, 1)->nullable()->after('cores');
        });

        Schema::table('vms', function (Blueprint $table) {
            $table->unsignedSmallInteger('cores')->nullable()->after('kind');
            $table->decimal('memory_gb', 8, 1)->nullable()->after('cores');
            $table->decimal('disk_gb', 10, 1)->nullable()->after('memory_gb');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['cpu', 'cores', 'memory_gb']);
        });

        Schema::table('vms', function (Blueprint $table) {
            $table->dropColumn(['cores', 'memory_gb', 'disk_gb']);
        });
    }
};
