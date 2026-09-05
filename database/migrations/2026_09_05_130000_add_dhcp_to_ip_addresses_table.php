<?php

use App\Models\IpAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ob eine Adresse per DHCP bezogen wird - als eigene Spalte.
 *
 * Bisher steckte es in der Bezeichnung: Wer dort genau "DHCP" schrieb, loeste
 * es aus. Das war eine versteckte Vereinbarung mit drei Fehlern. Sie stand
 * nirgends, "dhcp" klein geschrieben tat nichts, und die Bezeichnung war
 * belegt - fuer "Uplink Dachboden" war daneben kein Platz mehr.
 *
 * DHCP ist keine Bezeichnung, sondern eine Eigenschaft der Adresse. Als
 * Spalte laesst sie sich ankreuzen, kann nicht falsch geschrieben werden und
 * steht neben einer Bezeichnung statt an ihrer Stelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->boolean('dhcp')->default(false)->after('label');
        });

        // Was der Agent bisher als Bezeichnung gesetzt hat, zieht um. Die
        // Bezeichnung wird frei - sie trug ja nur die Marke.
        DB::table('ip_addresses')
            ->where('label', IpAddress::MARKE_DHCP)
            ->update(['dhcp' => true, 'label' => null]);
    }

    public function down(): void
    {
        // Zurueck in die Bezeichnung, damit der alte Stand die Marke
        // wiederfindet. Eine dort schon stehende Bezeichnung gewinnt: Sie
        // wurde von Hand geschrieben und ist die aeltere Angabe.
        DB::table('ip_addresses')
            ->where('dhcp', true)
            ->whereNull('label')
            ->update(['label' => IpAddress::MARKE_DHCP]);

        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->dropColumn('dhcp');
        });
    }
};
