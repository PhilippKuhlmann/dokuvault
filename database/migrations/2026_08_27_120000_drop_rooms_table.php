<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Tabelle "rooms" entfernen.
 *
 * Sie hatte nie eine Oberflaeche: kein Menuepunkt, keine Liste, kein Seeder,
 * kein Test - nur drei API-Routen und eine leere Tabelle. Der Rack-Standort
 * ist bewusst Freitext geworden (siehe create_racks_table), damit kam auch der
 * letzte denkbare Verwender nicht mehr in Frage.
 *
 * Die alte rack_cabinets-Tabelle zeigte per Fremdschluessel hierher; sie ist
 * bereits in drop_legacy_rack_tables entfernt worden, weshalb hier nichts mehr
 * im Weg steht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rooms');
    }

    public function down(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
};
