<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('username');
            $table->boolean('enabled')->nullable()->after('email');
            $table->string('agent_identifier')->nullable()->index();
        });

        Schema::table('ad_groups', function (Blueprint $table) {
            $table->string('agent_identifier')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ad_users', function (Blueprint $table) {
            $table->dropColumn(['email', 'enabled', 'agent_identifier']);
        });

        Schema::table('ad_groups', function (Blueprint $table) {
            $table->dropColumn('agent_identifier');
        });
    }
};
