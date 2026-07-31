<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt den unfertigen Rack-Ansatz von 2023: Die Tabellen waren über keine
 * Oberfläche erreichbar (leere Controller, API-Routen ohne Views) und enthalten
 * daher keine Nutzdaten. Der Nachfolger sind `racks` + `rack_items`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rack_cabinet_rack_device');
        Schema::dropIfExists('rack_devices');
        Schema::dropIfExists('rack_cabinets');
    }

    public function down(): void
    {
        // Bewusst leer: Die Alt-Tabellen waren nie in Benutzung, ein Rollback
        // soll sie nicht wieder anlegen (die Create-Migrationen bleiben erhalten).
    }
};
