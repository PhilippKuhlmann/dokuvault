<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Own right for the admin section "Statistik" (API-Auslastung and what
 * follows). It hung on admin_setting before - whoever may see the figures
 * had to be allowed to change the settings, and in the role editor there was
 * no box for it. Existing roles with admin_setting get it, nobody loses
 * access.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('permissions')->where('name', 'admin_statistik')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'admin_statistik',
                'description' => 'Statistik sehen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $vorbild = DB::table('permissions')->where('name', 'admin_setting')->value('id');
        if ($vorbild) {
            $rollen = DB::table('permission_role')->where('permission_id', $vorbild)->pluck('role_id');
            $schonDa = DB::table('permission_role')->where('permission_id', $id)->pluck('role_id');
            DB::table('permission_role')->insert(
                $rollen->diff($schonDa)->map(fn ($rolle) => ['permission_id' => $id, 'role_id' => $rolle])->values()->all()
            );
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'admin_statistik')->value('id');
        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
