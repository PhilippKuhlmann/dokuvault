<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservierte Adressbereiche eines Netzes.
 *
 * Ein Bereich belegt nichts. Er haelt fest, wofuer ein Stueck des Netzes
 * gedacht ist - "10.10.250.10 bis .20 sind fuer die Proxmox-Server" -, auch
 * wenn davon erst zwei Adressen vergeben sind. Ohne das sieht man im IPAM nur
 * die zwei Server und haelt den Rest fuer frei.
 *
 * Kein Papierkorb: Ein Bereich ist eine Notiz, kein Inventar. Wer ihn
 * versehentlich loescht, tippt zwei Adressen und eine Beschriftung neu -
 * eine Wiederherstellung waere mehr Aufwand als das.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');

            // Ein Bereich ohne sein Netz ergibt keinen Sinn - mit dem Netz
            // verschwindet er.
            $table->foreignId('network_id')->constrained('networks')->onDelete('cascade');

            // Als Text wie in ip_addresses: lesbar in der Datenbank, und
            // gerechnet wird ohnehin erst beim Zeichnen des Plans.
            $table->string('from_ip', 45);
            $table->string('to_ip', 45);

            $table->string('label');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['network_id', 'from_ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_ranges');
    }
};
