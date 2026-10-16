<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three rights for the checklists:
 *
 * - checklist_manage: work through lists, start offboarding, settings (IT)
 * - checklist_request: report a new employee via the form (customer)
 * - checklist_approve: approve sensitive drives (customer management)
 *
 * Roles with see_hidden (technicians) get all three, nobody else - customer
 * roles get them in the role editor.
 */
return new class extends Migration
{
    private array $rights = [
        'checklist_manage' => 'Checklisten bearbeiten',
        'checklist_request' => 'Neue Mitarbeiter melden',
        'checklist_approve' => 'Berechtigungen freigeben',
    ];

    public function up(): void
    {
        $model = DB::table('permissions')->where('name', 'see_hidden')->value('id');
        $roles = $model ? DB::table('permission_role')->where('permission_id', $model)->pluck('role_id') : collect();

        foreach ($this->rights as $name => $description) {
            $id = DB::table('permissions')->where('name', $name)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $existing = DB::table('permission_role')->where('permission_id', $id)->pluck('role_id');
            DB::table('permission_role')->insert(
                $roles->diff($existing)->map(fn ($role) => ['permission_id' => $id, 'role_id' => $role])->values()->all()
            );
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys($this->rights))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
