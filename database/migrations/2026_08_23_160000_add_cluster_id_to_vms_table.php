<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine VM laeuft auf einem Host oder in einem Cluster.
 *
 * In einem HA-Cluster wandert sie zwischen den Knoten - ein fester Host waere
 * dort nach der ersten Migration falsch dokumentiert. Der Cluster ist die
 * stabile Antwort, der Host bleibt fuer die Einzelmaschine.
 *
 * nullOnDelete wie beim Server: Verschwindet der Cluster, bleibt die VM und
 * verliert nur ihre Zuordnung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->foreignId('cluster_id')->nullable()->after('server_id')
                ->constrained('clusters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropForeign(['cluster_id']);
            $table->dropColumn('cluster_id');
        });
    }
};
