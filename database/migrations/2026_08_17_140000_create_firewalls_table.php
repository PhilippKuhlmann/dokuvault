<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die generische Firewall.
 *
 * Bisher gab es Firewalls nur herstellergebunden als Securepoint UTM. Wer eine
 * Sophos, Fortigate oder OPNsense dokumentiert, musste sie in "Router" pressen -
 * dabei ist die Firewall in fast jedem Netz das Geraet, nach dem zuerst gefragt
 * wird.
 *
 * Spalten wie beim Router, dazu drei Felder, die es nur hier braucht:
 * - firmware: Bei Firewalls ist der Versionsstand eine Sicherheitsfrage, nicht
 *   eine Randnotiz.
 * - management_url: Die Oberflaeche haengt selten auf der WAN-Adresse.
 * - subscription_until: Ohne gueltige Subscription bekommt eine UTM keine
 *   Signaturen mehr. Das ist ein anderes Datum als die Hardware-Garantie und
 *   deshalb ein eigenes Feld.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('name');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serialNumber')->nullable();
            $table->string('firmware')->nullable();
            $table->string('management_url')->nullable();
            $table->string('username')->nullable();
            // Wie bei Router und Switch: verschluesselt, deshalb text - ein
            // Chiffrat sprengt varchar(255) schon bei mittleren Kennwoertern.
            $table->text('password')->nullable();
            $table->string('port')->nullable();
            $table->date('subscription_until')->nullable();
            // Einbau in den Serverschrank, wie bei allen einbaubaren Geraeten.
            $table->unsignedTinyInteger('height_units')->default(1);
            $table->boolean('full_depth')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewalls');
    }
};
