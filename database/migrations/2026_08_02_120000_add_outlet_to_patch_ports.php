<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Dosennummer bekommt ein eigenes Feld. Vorher steckte sie zusammen mit
 * dem Raum in einem Freitextfeld ("EG 1.01 Empfang") - getrennt laesst sie
 * sich gezielt suchen und in Listen sauber untereinander stellen.
 *
 * Die Schreibweisen sind je Kunde verschieden ("EG 1.01", "A.12", "2.23"),
 * deshalb bewusst ein String ohne Format-Vorgabe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patch_ports', function (Blueprint $table) {
            $table->string('outlet')->nullable()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('patch_ports', function (Blueprint $table) {
            $table->dropColumn('outlet');
        });
    }
};
