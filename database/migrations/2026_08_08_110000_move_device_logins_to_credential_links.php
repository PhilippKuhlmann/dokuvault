<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NAS- und Recorder-Logins in den allgemeinen Mechanismus überführen.
 *
 * Beide Tabellen waren genau das, was `credential_links` jetzt generisch kann -
 * Zugangsdaten mit fest verdrahteter Geräte-ID, nur ohne Mehrfachverwendung und
 * ohne PDF-Abschnitt. Nach dem Umzug stehen sie unter "Logins Allgemein", hängen
 * per Verknüpfung am selben Gerät wie vorher und erscheinen im Export.
 *
 * Das Passwort wird als Geheimtext kopiert, nicht entschlüsselt und neu
 * verschlüsselt: gleicher Schlüssel, gleiches Format - und eine kaputte Zeile
 * bringt die Migration nicht zum Stehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->umziehen('login_nas', 'nas_id', 'nas', 'App\Models\NAS');
        $this->umziehen('login_recorders', 'recorder_id', 'recorders', 'App\Models\Recorder');

        Schema::dropIfExists('login_nas');
        Schema::dropIfExists('login_recorders');

        $this->rechteEntfernen();
    }

    /**
     * @param  string  $tabelle  Quelltabelle
     * @param  string  $fremdschluessel  Spalte mit der Geräte-ID
     * @param  string  $geraeteTabelle  Tabelle des Geräts (für den Namen)
     * @param  string  $morphTyp  Klassenname, wie ihn Eloquent in credentialable_type schreibt
     */
    private function umziehen(string $tabelle, string $fremdschluessel, string $geraeteTabelle, string $morphTyp): void
    {
        if (! Schema::hasTable($tabelle) || ! Schema::hasTable('credential_links')) {
            return;
        }

        $hatBeschreibung = Schema::hasColumn($tabelle, 'description');
        $jetzt = now();

        // Auch weggeworfene Einträge kommen mit: sie liegen im Papierkorb und
        // sollen sich weiterhin wiederherstellen lassen - nur eben als
        // "Login Allgemein" statt unter dem alten Typ.
        foreach (DB::table($tabelle)->orderBy('id')->cursor() as $alt) {
            $geraeteName = DB::table($geraeteTabelle)->where('id', $alt->$fremdschluessel)->value('name');

            // Ohne Namen am Gerät bliebe die Zeile in der Liste namenlos.
            $name = $geraeteName ?: ucfirst($geraeteTabelle).' #'.$alt->$fremdschluessel;
            if (! empty($alt->username)) {
                $name .= ' ('.$alt->username.')';
            }

            $loginId = DB::table('login_generals')->insertGetId([
                'customer_id' => $alt->customer_id,
                'name' => $name,
                'description' => $hatBeschreibung ? $alt->description : null,
                'username' => $alt->username,
                'password' => $alt->password,
                'hidden' => $alt->hidden ?? false,
                'created_at' => $alt->created_at,
                'updated_at' => $alt->updated_at,
                'deleted_at' => $alt->deleted_at ?? null,
            ]);

            DB::table('credential_links')->insert([
                'customer_id' => $alt->customer_id,
                'login_general_id' => $loginId,
                'credentialable_type' => $morphTyp,
                'credentialable_id' => $alt->$fremdschluessel,
                'note' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        }
    }

    /** Die acht Berechtigungen der beiden Typen samt Rollenzuordnung. */
    private function rechteEntfernen(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->where('name', 'like', 'loginnas\_%')
            ->orWhere('name', 'like', 'loginrecorder\_%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    /**
     * Zurück gibt es nur die leeren Tabellen: Welche Einträge in login_generals
     * aus welcher Quelle stammen, steht nirgends - und sie zurückzuraten wäre
     * schlimmer als sie stehen zu lassen. Die Daten bleiben nutzbar, nur eben
     * unter "Logins Allgemein".
     */
    public function down(): void
    {
        if (! Schema::hasTable('login_nas')) {
            Schema::create('login_nas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
                $table->foreignId('nas_id')->constrained('nas')->onDelete('cascade');
                $table->string('description')->nullable();
                $table->string('username');
                $table->string('password');
                $table->boolean('hidden')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('login_recorders')) {
            Schema::create('login_recorders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
                $table->foreignId('recorder_id')->constrained('recorders')->onDelete('cascade');
                $table->string('username');
                $table->string('password');
                $table->boolean('hidden')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
};
