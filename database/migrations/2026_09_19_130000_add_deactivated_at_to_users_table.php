<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seit wann dieser Zugang gesperrt ist.
 *
 * Ein Zeitstempel und kein Schalter: Bei einem ausgeschiedenen Mitarbeiter ist
 * "seit wann" die Frage, die spaeter jemand stellt - und der Zeitpunkt kostet
 * nichts gegenueber einem Ja/Nein.
 *
 * Geloescht wird der Zugang dabei ausdruecklich nicht: An ihm haengen
 * Protokolleintraege, und ein geloeschter Benutzer macht aus "Rita hat den
 * Serverschrank geaendert" ein "jemand".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('invitation_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
