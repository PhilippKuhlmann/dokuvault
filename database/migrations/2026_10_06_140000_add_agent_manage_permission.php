<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Own right for the agent page (tokens, downloads, installed agents) and
 * the agents tile on the dashboard. It hung on see_hidden ("Verstecke Objekte
 * sehen") - a different matter, and in the role editor nobody would look
 * for agents there. Roles with see_hidden get it, nobody loses access.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('permissions')->where('name', 'agent_manage')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'agent_manage',
                'description' => 'Agenten verwalten',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $vorbild = DB::table('permissions')->where('name', 'see_hidden')->value('id');
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
        $id = DB::table('permissions')->where('name', 'agent_manage')->value('id');
        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
