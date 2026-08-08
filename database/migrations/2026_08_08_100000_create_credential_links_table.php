<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_links', function (Blueprint $table) {
            $table->id();
            // customer_id denormalisiert wie bei ip_addresses: macht die
            // Mandantenpruefung ohne Join ueber das polymorphe Ziel moeglich.
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('login_general_id')->constrained('login_generals')->onDelete('cascade');
            $table->morphs('credentialable');
            // Wofuer genau die Zugangsdaten an diesem Geraet gelten - "SSH root",
            // "iDRAC", "Konsole". Der Benutzername steht am Login selbst.
            $table->string('note')->nullable();
            $table->timestamps();

            // Ein Login haengt hoechstens einmal am selben Geraet.
            $table->unique(['login_general_id', 'credentialable_type', 'credentialable_id'], 'credential_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_links');
    }
};
