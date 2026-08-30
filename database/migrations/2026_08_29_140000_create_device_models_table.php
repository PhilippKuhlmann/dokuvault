<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geraetemodelle: was eine "APC Smart-UPS 1500" ist, unabhaengig davon,
     * bei welchem Kunden eine steht.
     *
     * Bewusst ohne customer_id. Ein Foto der Frontblende beschreibt das Modell,
     * nicht das Exemplar - wer es bei einem Kunden hinterlegt, hinterlegt es
     * fuer alle. Genau das ist der Zweck: dieselbe USV soll nicht zwanzigmal
     * fotografiert werden.
     *
     * Und bewusst ohne Verweisspalte an den neun Geraetetabellen: Hersteller
     * und Modell stehen dort laengst als Felder. Der Katalog wird darueber
     * gefunden, nicht ueber eine zusaetzliche Auswahl - bestehende Geraete
     * bekommen ihr Bild damit rueckwirkend, ohne dass jemand nachpflegt.
     */
    public function up(): void
    {
        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            // Schluessel aus config('custom.rack_device_types'). Ohne ihn
            // passte ein Switch "RS-1000" auf einen Recorder gleichen Namens.
            $table->string('device_type', 40);
            $table->string('manufacturer');
            // Das E-Mail-Archiv fuehrt nur einen Hersteller, kein Modell.
            $table->string('model')->nullable();

            // Verglichen wird ueber normalisierte Schluessel: kleingeschrieben,
            // getrimmt, Mehrfachleerzeichen zusammengezogen. "APC " und "apc"
            // sollen dasselbe treffen - und zwar auf MySQL wie auf SQLite
            // gleich, deren Sortierregeln sich bei = unterscheiden.
            $table->string('manufacturer_key');
            $table->string('model_key')->default('');

            $table->unsignedTinyInteger('height_units')->default(1);
            $table->boolean('full_depth')->default(true);
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->unique(['device_type', 'manufacturer_key', 'model_key'], 'device_models_kennung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_models');
    }
};
