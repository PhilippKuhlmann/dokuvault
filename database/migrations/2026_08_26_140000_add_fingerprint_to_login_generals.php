<?php

use App\Models\SshKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Der Fingerprint eines SSH-Schluessels.
 *
 * Abgeleitet, nicht eingegeben: Er ergibt sich vollstaendig aus dem
 * oeffentlichen Schluessel und wird bei jedem Speichern neu berechnet. Er steht
 * trotzdem als Spalte da, damit man danach suchen kann - genau der Weg vom
 * "SHA256:..." aus einer authorized_keys zurueck zum dokumentierten Schluessel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_generals', function (Blueprint $table) {
            $table->string('fingerprint')->nullable()->after('public_key')->index();
        });

        // Was schon dokumentiert ist, bekommt ihn nachgetragen - sonst faende
        // die Suche gerade die aeltesten Schluessel nicht.
        foreach (SshKey::whereNotNull('public_key')->cursor() as $schluessel) {
            DB::table('login_generals')->where('id', $schluessel->id)
                ->update(['fingerprint' => SshKey::fingerprintVon($schluessel->public_key)]);
        }
    }

    public function down(): void
    {
        Schema::table('login_generals', function (Blueprint $table) {
            $table->dropIndex(['fingerprint']);
            $table->dropColumn('fingerprint');
        });
    }
};
