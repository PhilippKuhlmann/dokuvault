<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ftp_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('host')->nullable();
            $table->string('description')->nullable();
            // Kein Benutzer, kein Kennwort am Server: Ein FTP-Server hat mehrere
            // Zugaenge, sie haengen ueber credential_links an "Logins Allgemein"
            // wie bei jedem anderen Geraet.
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ftp_servers');
    }
};
