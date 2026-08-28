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
        Schema::create('login_generals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->boolean('hidden')->default(false);
            // Indiziert: Beide Listen filtern bei jedem Aufruf danach.
            $table->string('kind')->default('password')->index();
            $table->string('key_type')->nullable();
            // Text, nicht string: Ein RSA-4096-Schluessel ist rund 750 Zeichen
            // im oeffentlichen und ueber 3000 im privaten Teil.
            $table->text('public_key')->nullable();
            $table->text('private_key')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_generals');
    }
};
