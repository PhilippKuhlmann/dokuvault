<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last time step of an accepted one-time code. A code is valid for
 * several steps (clock drift), so without this a code someone watched being
 * typed could be used again inside its window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_last_step');
        });
    }
};
