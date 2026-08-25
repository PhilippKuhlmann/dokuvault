<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verschluesselte Kennwortspalten auf text vergroessern.
 *
 * Die Felder werden verschluesselt gespeichert, die Spalten waren aber
 * varchar(255) - also so breit wie frueher der Klartext. Ein Chiffrat ist
 * deutlich laenger als sein Klartext: 16 Zeichen ergeben 228, ab 32 Zeichen
 * sind es 256 und damit mehr, als hineinpasst.
 *
 * Auf MySQL im strict-Modus endet das mit "Data too long for column" - das
 * Speichern schlaegt fehl, sobald jemand ein Kennwort ab 32 Zeichen eintraegt.
 * Bei erzeugten Kennwoertern ist das die Regel und nicht die Ausnahme. Ohne
 * strict-Modus waere es schlimmer: Dann wuerde still abgeschnitten, und das
 * Kennwort waere beim naechsten Lesen unbrauchbar.
 *
 * Die Testsuite laeuft auf SQLite und kaeme dem nie auf die Spur: Dort ist
 * varchar(255) keine Grenze, sondern eine Notiz.
 */
return new class extends Migration
{
    /**
     * Alle Spalten, hinter denen im Model ein Crypt-Attribut steht.
     * Vier weitere (ad_domains.dsrmpassword, backups.password,
     * internet_connections.pppoe_password, securepoint_umas.encryptionkey)
     * sind bereits text und fehlen deshalb hier.
     */
    private const FELDER = [
        'accesspoints' => ['password'],
        'ad_users' => ['password'],
        'cameras' => ['password'],
        'computers' => ['remotePassword'],
        'dect' => ['password'],
        'dyndns' => ['password'],
        'iot_devices' => ['password'],
        'license_software' => ['password'],
        'login_generals' => ['password'],
        'login_websites' => ['password'],
        'nas' => ['password'],
        'network_switches' => ['password'],
        'other_clients' => ['password'],
        'phone_systems' => ['password'],
        'phones' => ['password'],
        'recorders' => ['password'],
        'routers' => ['password'],
        'securepoint_umas' => ['password'],
        'securepoint_utms' => ['password', 'cloudBackupPassword', 'uscpin'],
        'servers' => ['bmcPassword', 'remotePassword'],
        'vms' => ['remotePassword'],
        'wifis' => ['password'],
    ];

    public function up(): void
    {
        $this->jedeSpalte(function (string $tabelle, string $spalte, bool $nullable) {
            Schema::table($tabelle, function (Blueprint $table) use ($spalte, $nullable) {
                $table->text($spalte)->nullable($nullable)->change();
            });
        });
    }

    public function down(): void
    {
        $this->jedeSpalte(function (string $tabelle, string $spalte, bool $nullable) {
            // Zurueck geht nur, solange jeder Wert wieder hineinpasst. Sonst
            // richtet die Ruecknahme genau den Schaden an, den diese Migration
            // verhindern soll.
            $zuLang = DB::table($tabelle)->whereRaw("LENGTH(`$spalte`) > 255")->count();

            if ($zuLang > 0) {
                throw new RuntimeException(
                    "{$tabelle}.{$spalte}: {$zuLang} Eintraege sind laenger als 255 Zeichen und wuerden beim ".
                    'Verkleinern abgeschnitten. Erst die betroffenen Kennwoerter kuerzen.'
                );
            }

            Schema::table($tabelle, function (Blueprint $table) use ($spalte, $nullable) {
                $table->string($spalte)->nullable($nullable)->change();
            });
        });
    }

    /**
     * Ob eine Spalte null erlaubt, steht im Schema - nicht in einer zweiten
     * Liste hier, die irgendwann von der Wirklichkeit abweicht. Ohne diese
     * Uebernahme machte ein change() aus einer optionalen Spalte eine
     * Pflichtspalte.
     */
    private function jedeSpalte(callable $arbeit): void
    {
        foreach (self::FELDER as $tabelle => $spalten) {
            if (! Schema::hasTable($tabelle)) {
                continue;
            }

            $vorhanden = collect(Schema::getColumns($tabelle))->keyBy('name');

            foreach ($spalten as $spalte) {
                if (! isset($vorhanden[$spalte])) {
                    continue;
                }

                $arbeit($tabelle, $spalte, (bool) $vorhanden[$spalte]['nullable']);
            }
        }
    }
};
