<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Firewall nimmt die Securepoint UTM auf.
 *
 * Eine UTM ist eine Firewall - zwei Objekte fuer dieselbe Geraetegattung
 * bedeuteten zwei Eintraege in Sidebar, Dashboard, PDF und Suche, und ein
 * Geraetetausch von Securepoint auf Sophos hiess loeschen und neu anlegen.
 *
 * Vier Felder sind wirklich Securepoint-eigen und kommen als nullable Spalten
 * dazu; sichtbar sind sie nur, wenn der Hersteller Securepoint ist - dasselbe
 * Muster wie bei servers.form_factor, das Einbautiefe und Hoeheneinheiten
 * steuert. Zwei weitere Felder der UTM brauchen keine eigene Spalte:
 * urlAdmin ist die Verwaltungsoberflaeche, die es schon gibt, und type
 * ("Appliance" oder "VM") ist eine Bauform, die fuer jede Firewall taugt -
 * eine OPNsense laeuft oft als VM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            // Bauform: entscheidet auch, ob ein Einbau in den Schrank ueberhaupt
            // in Frage kommt.
            $table->string('form_factor')->default('appliance')->after('model');

            // Benutzerportal und externer Zugang - bei einer UTM drei getrennte
            // Oberflaechen, bei anderen Herstellern meist nur eine.
            $table->string('url_user')->nullable()->after('management_url');
            $table->string('url_external')->nullable()->after('url_user');

            // Verschluesselt, deshalb text: Ein Chiffrat sprengt varchar(255).
            $table->text('usc_pin')->nullable()->after('password');
            $table->text('cloud_backup_password')->nullable()->after('usc_pin');
        });
    }

    public function down(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            $table->dropColumn(['form_factor', 'url_user', 'url_external', 'usc_pin', 'cloud_backup_password']);
        });
    }
};
