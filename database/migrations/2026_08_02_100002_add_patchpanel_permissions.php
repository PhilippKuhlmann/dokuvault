<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bestehende Installationen bekommen die vier Patchfeld-Berechtigungen per Migration
 * (der PermissionRoleSeeder laeuft dort nicht erneut). Admin- und Techniker-Rolle
 * erhalten sie sofort; anderen Rollen weist sie ein Admin ueber die Rollen-Seite zu.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'patchpanel_viewAny' => 'Patchfeld sehen',
        'patchpanel_create' => 'Patchfeld erstellen',
        'patchpanel_update' => 'Patchfeld bearbeiten',
        'patchpanel_delete' => 'Patchfeld löschen',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        // Frische Installation: Tabelle ist leer, gleich laeuft der PermissionRoleSeeder
        // und legt alle Rechte an - hier nichts tun, sonst kollidiert dessen forceCreate.
        if (DB::table('permissions')->count() === 0) {
            return;
        }

        foreach (self::PERMISSIONS as $name => $description) {
            $id = DB::table('permissions')->where('name', $name)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'description' => $description,
                ]);

            foreach ([Role::IS_ADMIN, Role::IS_TECHNIKER] as $roleId) {
                if (DB::table('roles')->where('id', $roleId)->exists()
                    && ! DB::table('permission_role')->where(['permission_id' => $id, 'role_id' => $roleId])->exists()) {
                    DB::table('permission_role')->insert(['permission_id' => $id, 'role_id' => $roleId]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
