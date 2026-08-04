<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rack_items', function (Blueprint $table) {
            // Vorder- oder Rueckseite. Bestand liegt vorne - dort wurde bisher
            // ausschliesslich eingebaut.
            $table->string('side', 5)->default('front')->after('rack_id');

            // Beim Einbau vom Geraet bzw. Katalogelement kopiert, wie name und
            // appearance: Eine spaetere Aenderung am Geraet soll den Schrank
            // nicht rueckwirkend umbauen.
            $table->boolean('full_depth')->default(true)->after('height_units');
        });

        Schema::table('rack_catalog_items', function (Blueprint $table) {
            // Steckdosenleisten und Kabelbuegel sitzen typischerweise hinten und
            // lassen Platz davor - das gehoert an den Katalogeintrag.
            $table->boolean('full_depth')->default(true)->after('height_units');
        });

        // Was typischerweise hinten sitzt und vorne Platz laesst. Nur diese
        // beiden - alles andere (Blindplatten, Fachboeden, Patchfelder) geht
        // ueber die volle Tiefe oder verdeckt sie zumindest.
        DB::table('rack_catalog_items')
            ->whereIn('name', ['Steckdosenleiste (PDU)', 'Rangierfeld'])
            ->update(['full_depth' => false]);
    }

    public function down(): void
    {
        Schema::table('rack_items', function (Blueprint $table) {
            $table->dropColumn(['side', 'full_depth']);
        });

        Schema::table('rack_catalog_items', function (Blueprint $table) {
            $table->dropColumn('full_depth');
        });
    }
};
