<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rack_catalog_items', function (Blueprint $table) {
            // Pfad auf der local-Disk. Ist er gesetzt, zeichnet die Frontansicht
            // nicht mehr, sondern zeigt das Foto - die Darstellung bleibt als
            // Rueckfall stehen, falls das Bild wieder entfernt wird.
            $table->string('image_path')->nullable()->after('appearance');
        });

        Schema::table('rack_items', function (Blueprint $table) {
            // Bewusst ohne Fremdschluessel: Diese Tabelle kopiert beim Einbau
            // alles Beschreibende (Bezeichnung, Darstellung, Hoehe), damit ein
            // spaeter geaenderter Katalogeintrag bestehende Racks nicht umbaut.
            // Der Verweis dient allein dem Bild; faellt der Eintrag weg, faellt
            // das Foto weg und die gezeichnete Blende tritt an seine Stelle.
            $table->unsignedBigInteger('rack_catalog_item_id')->nullable()->index()->after('appearance');
        });
    }

    public function down(): void
    {
        Schema::table('rack_catalog_items', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('rack_items', function (Blueprint $table) {
            $table->dropColumn('rack_catalog_item_id');
        });
    }
};
