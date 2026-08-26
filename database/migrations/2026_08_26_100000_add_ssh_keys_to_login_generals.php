<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSH-Schluessel in dieselbe Tabelle wie die Logins.
 *
 * Ein Schluessel ist inhaltlich ein Zugangsdatum wie ein Kennwort: Er gehoert
 * zu einem Benutzer und gilt auf einem oder mehreren Systemen. Er teilt sich
 * deshalb Tabelle und Verknuepfung mit den Logins - so haengt er ueber
 * credential_links an Servern und VMs, ohne dass das Muster ein zweites Mal
 * gebaut werden muss.
 *
 * Getrennt werden beide nur in der Anzeige, ueber 'kind'. Zwei Listen, weil
 * "welcher Key gilt wo?" eine andere Frage ist als "wie lautet das Kennwort?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_generals', function (Blueprint $table) {
            // Indiziert: Beide Listen filtern bei jedem Aufruf danach.
            $table->string('kind')->default('password')->index()->after('customer_id');
            $table->string('key_type')->nullable()->after('password');
            // Text, nicht string: Ein RSA-4096-Schluessel ist rund 750 Zeichen
            // im oeffentlichen und ueber 3000 im privaten Teil.
            $table->text('public_key')->nullable()->after('key_type');
            $table->text('private_key')->nullable()->after('public_key');
        });
    }

    public function down(): void
    {
        Schema::table('login_generals', function (Blueprint $table) {
            $table->dropColumn(['kind', 'key_type', 'public_key', 'private_key']);
        });
    }
};
