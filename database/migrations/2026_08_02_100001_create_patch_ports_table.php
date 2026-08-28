<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patch_ports', function (Blueprint $table) {
            $table->id();
            // customer_id denormalisiert wie bei ip_addresses: macht die
            // Mandantenpruefung und die globale Suche ohne Join moeglich.
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('patch_panel_id')->constrained('patch_panels')->onDelete('cascade');
            $table->unsignedSmallInteger('number');
            // Netzwerkdose bzw. Raum, Freitext - so wie racks.location.
            $table->string('label')->nullable();
            // Gegenstelle: Switch aus der Doku plus dessen Portnummer.
            $table->foreignId('network_switch_id')->nullable()
                ->constrained('network_switches')->nullOnDelete();
            // Bewusst String: gestapelte Switches nummerieren "1/0/12".
            $table->string('switch_port')->nullable();
            $table->string('note')->nullable();
            $table->string('outlet')->nullable();
            $table->timestamps();

            $table->unique(['patch_panel_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patch_ports');
    }
};
