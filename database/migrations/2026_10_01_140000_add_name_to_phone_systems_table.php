<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A phone system was only "Auerswald" - with two at one customer, or in the
 * selection of a SIP account, there was no telling them apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_systems', function (Blueprint $table) {
            $table->string('name')->nullable()->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('phone_systems', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
