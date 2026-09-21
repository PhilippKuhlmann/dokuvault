<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_tokens', function (Blueprint $table) {
            // Nullable mit Bedacht: Bestehende Token haben kein Ablaufdatum und
            // sollen weiterlaufen (Altbestand), sonst fielen alle laufenden
            // Agenten mit dieser Migration schlagartig aus. Neue Token bekommen
            // im Controller eine Pflicht-Frist; ein null-Wert heisst darum
            // "vor der Frist angelegt", nicht "absichtlich unbegrenzt".
            $table->timestamp('expires_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('agent_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
