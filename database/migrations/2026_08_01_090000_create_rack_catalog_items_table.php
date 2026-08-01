<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Der Rack-Katalog stand vorher fest in config/custom.php und war damit nur
     * per Code-Aenderung erweiterbar. Als Tabelle laesst er sich im Adminbereich
     * pflegen.
     *
     * Die Standardeintraege legt die Migration selbst an - so hat auch eine
     * bestehende Installation nach `php artisan migrate` sofort einen
     * vollstaendigen Katalog und braucht keinen zusaetzlichen Seeder-Lauf.
     */
    private const DEFAULTS = [
        ['Patchfeld 24 Port', 1],
        ['Patchfeld 48 Port', 2],
        ['LWL-Patchfeld', 1],
        ['Rangierfeld', 1],
        ['Kabeldurchführung', 1],
        ['Fachboden 1 HE', 1],
        ['Fachboden 2 HE', 2],
        ['Blindplatte 1 HE', 1],
        ['Blindplatte 2 HE', 2],
        ['Blindplatte 3 HE', 3],
        ['Steckdosenleiste (PDU)', 1],
    ];

    public function up(): void
    {
        Schema::create('rack_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('height_units')->default(1);
            // Reihenfolge in der Palette; gleiche Werte werden nach Name sortiert.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('rack_catalog_items')->insert(
            collect(self::DEFAULTS)->map(fn (array $entry, int $i) => [
                'name' => $entry[0],
                'height_units' => $entry[1],
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_catalog_items');
    }
};
