<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-customer start password for new AD users.
 *
 * - generated: the AD script builds it from a rule (length, number of upper
 *   case letters, digits and special characters from an allowed set; the
 *   rest lower case) - readable,
 *   no look-alikes like l/1 or O/0. It never stands in the downloaded file.
 * - fixed: one simple password for every new user (encrypted here); the
 *   user must change it at first sign-in.
 *
 * force_change_default seeds the checkbox of each new checklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('password_mode', 20)->default('generated');
            $table->unsignedTinyInteger('password_length')->default(8);
            $table->unsignedTinyInteger('password_uppercase')->default(1);
            $table->unsignedTinyInteger('password_special_count')->default(1);
            $table->unsignedTinyInteger('password_digits')->default(2);
            $table->string('password_specials', 20)->default('!-');
            $table->text('fixed_password')->nullable();
            $table->boolean('force_change_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_settings');
    }
};
