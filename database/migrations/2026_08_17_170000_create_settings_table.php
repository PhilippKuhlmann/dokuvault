<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einstellungen, die eine Installation zur Laufzeit aendert.
 *
 * Absichtlich Schluessel und Wert statt einer Spalte je Einstellung: Es sind
 * wenige, sie werden selten gelesen (und dann aus dem Cache), und jede neue
 * kostet sonst eine Migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
