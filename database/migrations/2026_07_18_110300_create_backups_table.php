<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('software')->nullable();
            $table->string('source')->nullable();
            $table->string('destination')->nullable();
            $table->string('schedule')->nullable();
            $table->string('retention')->nullable();
            $table->date('last_success')->nullable();
            $table->text('password')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
