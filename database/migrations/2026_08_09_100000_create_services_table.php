<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog der Dienste, gepflegt in der Administration.
 *
 * Die Dienste am Gerät bleiben eine Freitextspalte (`services`, komma-getrennt).
 * Der Katalog gibt nur vor, was zur Auswahl steht und welche Farbe ein Dienst in
 * den Listen bekommt - Bestandseinträge, die (noch) nicht im Katalog stehen,
 * werden weiterhin angezeigt, nur neutral eingefärbt. So braucht es keine
 * Datenmigration und niemand verliert dokumentierte Dienste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->default('#e5e7eb');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
