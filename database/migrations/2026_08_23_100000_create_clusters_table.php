<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-Cluster: welche Server zusammengehoeren und mit welcher Technik.
 *
 * Eigenes Objekt statt eines Freitextfelds am Server: Die Technik (Ceph,
 * Replikation ...) gilt fuer den Cluster als Ganzes, nicht je Knoten - an
 * jedem Server gepflegt stuende sie mehrfach da und koennte auseinanderlaufen.
 *
 * cluster_id am Server ist nullable: Die allermeisten Server stehen allein.
 * nullOnDelete statt cascade - loescht jemand den Cluster, sollen die Server
 * bleiben und nur ihre Zugehoerigkeit verlieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('name');
            // Schluessel aus config('custom.cluster_types'), kein DB-Enum: Ein
            // neuer Typ ist damit eine Zeile in der Konfiguration statt einer
            // Migration.
            $table->string('type')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->foreignId('cluster_id')->nullable()->after('site_id')
                ->constrained('clusters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['cluster_id']);
            $table->dropColumn('cluster_id');
        });

        Schema::dropIfExists('clusters');
    }
};
