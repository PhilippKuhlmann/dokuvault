<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuer die gezeichnete Frontansicht muss bekannt sein, *wie* ein Einbau
     * aussieht. Bei dokumentierten Geraeten ergibt sich das aus dem Typ, bei
     * Katalogelementen nicht - "Lüftereinheit" laesst sich nicht erraten.
     * Deshalb waehlt der Admin die Darstellung je Katalogeintrag aus.
     *
     * rack_items bekommt die Spalte ebenfalls: die Darstellung wird beim Einbau
     * kopiert, genau wie die Bezeichnung. Ein spaeter geaenderter Katalogeintrag
     * veraendert damit keine bestehende Bestueckung.
     */
    private const BY_NAME = [
        'Patchfeld 24 Port' => 'patchpanel',
        'Patchfeld 48 Port' => 'patchpanel',
        'LWL-Patchfeld' => 'patchpanel',
        'Rangierfeld' => 'cablering',
        'Kabeldurchführung' => 'brush',
        'Fachboden 1 HE' => 'shelf',
        'Fachboden 2 HE' => 'shelf',
        'Steckdosenleiste (PDU)' => 'pdu',
    ];

    public function up(): void
    {
        Schema::table('rack_catalog_items', function (Blueprint $table) {
            $table->string('appearance')->default('blank')->after('height_units');
        });

        Schema::table('rack_items', function (Blueprint $table) {
            $table->string('appearance')->nullable()->after('name');
        });

        foreach (self::BY_NAME as $name => $appearance) {
            DB::table('rack_catalog_items')->where('name', $name)->update(['appearance' => $appearance]);
        }

        // Bereits verbaute Katalogelemente nachziehen (Geraete brauchen es nicht,
        // deren Darstellung kommt aus dem Geraetetyp).
        foreach (self::BY_NAME as $name => $appearance) {
            DB::table('rack_items')->whereNull('device_type')->where('name', $name)
                ->update(['appearance' => $appearance]);
        }
    }

    public function down(): void
    {
        Schema::table('rack_catalog_items', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });

        Schema::table('rack_items', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
