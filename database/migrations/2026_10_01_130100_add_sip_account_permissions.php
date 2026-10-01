<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The four sipaccount permissions for existing installations. Fresh ones get
 * them through the PermissionRoleSeeder (config custom.permissions).
 *
 * Whoever may maintain the phone system gets the same right on SIP accounts:
 * both sit under "Telefon" side by side.
 */
return new class extends Migration
{
    private const RECHTE = [
        'viewAny' => 'sehen',
        'create' => 'erstellen',
        'update' => 'bearbeiten',
        'delete' => 'löschen',
    ];

    public function up(): void
    {
        foreach (self::RECHTE as $recht => $beschreibung) {
            $id = DB::table('permissions')->where('name', "sipaccount_{$recht}")->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => "sipaccount_{$recht}",
                    'description' => "SipAccount {$beschreibung}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $vorbild = DB::table('permissions')->where('name', "phonesystem_{$recht}")->value('id');

            if (! $vorbild) {
                continue;
            }

            $rollen = DB::table('permission_role')->where('permission_id', $vorbild)->pluck('role_id');
            $schonDa = DB::table('permission_role')->where('permission_id', $id)->pluck('role_id');

            DB::table('permission_role')->insert(
                $rollen->diff($schonDa)
                    ->map(fn ($rolle) => ['permission_id' => $id, 'role_id' => $rolle])
                    ->values()->all()
            );
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('name', 'like', 'sipaccount\_%')->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
