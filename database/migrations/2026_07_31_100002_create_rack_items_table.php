<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('racks')->onDelete('cascade');
            // Unterste belegte Hoeheneinheit; Racks zaehlen von unten (1 = ganz unten).
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('height_units')->default(1);
            // Entweder ein verknuepftes dokumentiertes Geraet (morph) ...
            $table->nullableMorphs('device');
            // ... oder ein passives Katalogelement (nur Bezeichnung).
            $table->string('name')->nullable();
            $table->timestamps();

            // Ein dokumentiertes Geraet darf insgesamt nur einmal verbaut sein.
            $table->unique(['device_type', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_items');
    }
};
