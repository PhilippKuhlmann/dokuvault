<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wann der Eingeladene sein Kennwort gesetzt hat - der Moment, in dem die
 * Einladung abgeschlossen ist und ihr Link nicht mehr traegt.
 *
 * Warum eine eigene Spalte: invited_at wird beim Einloesen geleert, damit die
 * Einladung nicht als offen stehen bleibt. Danach sieht ein Zugang, der
 * eingeladen wurde und laengst drin ist, genauso aus wie einer, der nie eine
 * Einladung bekam - beide haben invited_at = NULL. Aus password_resets ist es
 * auch nicht abzulesen: Die Zeile ist beim Einloesen weg, genau darum wird
 * der Link ja ungueltig.
 *
 * Fuer Einladungen, die vor dieser Migration eingeloest wurden, bleibt die
 * Spalte leer. Nachtragen liesse sie sich nur raten - der Zeitpunkt ist
 * nirgends mehr aufgehoben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('invitation_accepted_at');
        });
    }
};
