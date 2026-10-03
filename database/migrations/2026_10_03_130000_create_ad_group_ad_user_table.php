<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is in which AD group. Filled by the AD agent (direct members) and
 * editable by hand from either side - the user form and the group form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_group_ad_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_group_id')->constrained('ad_groups')->cascadeOnDelete();
            $table->foreignId('ad_user_id')->constrained('ad_users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ad_group_id', 'ad_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_group_ad_user');
    }
};
