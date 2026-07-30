<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('current_step')->nullable();
            $table->json('completed_steps')->nullable();
            $table->json('skipped_steps')->nullable();
            // Was dieser Durchlauf angelegt hat, je Schritt eine Liste von IDs -
            // fuer die Abschlussuebersicht, kein Ersatz fuer den Papierkorb.
            $table->json('created_records')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_runs');
    }
};
