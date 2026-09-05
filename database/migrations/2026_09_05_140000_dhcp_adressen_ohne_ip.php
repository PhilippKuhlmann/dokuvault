<?php

use App\Models\Network;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eine per DHCP versorgte Zuordnung hat keine Adresse.
 *
 * Bisher stand dort die zuletzt gesehene. Angezeigt wurde sie nicht mehr, aber
 * gespeichert - und damit behauptete die Doku weiter etwas, das nach dem
 * naechsten Neustart woanders steht. Was zaehlt, ist das Netz: "dieser
 * Accesspoint haengt per DHCP im VLAN 40".
 *
 * Deshalb wird 'address' optional. Fuer die vorhandenen DHCP-Zeilen wird das
 * Netz aus der bisherigen Adresse abgeleitet, sofern es nicht schon steht -
 * danach faellt die Adresse weg. Sie laesst sich nicht zurueckholen; sie war
 * eine Momentaufnahme und ist beim naechsten Agentenlauf ohnehin eine andere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
        });

        foreach (DB::table('ip_addresses')->where('dhcp', true)->get() as $zeile) {
            $netz = $zeile->network_id;

            if (! $netz && $zeile->address) {
                $netz = Network::fuerAdresse($zeile->customer_id, null, $zeile->address)?->id;
            }

            DB::table('ip_addresses')->where('id', $zeile->id)
                ->update(['network_id' => $netz, 'address' => null]);
        }
    }

    public function down(): void
    {
        // Ohne Adresse laesst sich die Spalte nicht wieder auf NOT NULL
        // stellen. Zeilen ohne Adresse sind genau die, um die es ging - sie
        // verschwinden beim Zurueckrollen.
        DB::table('ip_addresses')->whereNull('address')->delete();

        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
        });
    }
};
