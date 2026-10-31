<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The permission was called 'admin_statistik' - the last German
     * permission name. Renamed with the rest of the code.
     *
     * It has to be a migration and not just a change in config: the name is a
     * row in the permissions table, and the gates ask for exactly that
     * string. Without this step every statistics page would answer 403 on
     * installations that already hold the row.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'admin_statistik')
            ->update(['name' => 'admin_stats']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'admin_stats')
            ->update(['name' => 'admin_statistik']);
    }
};
