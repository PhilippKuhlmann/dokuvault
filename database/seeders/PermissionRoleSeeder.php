<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Rollen

        $admin = Role::forceCreate([
            'id' => 1,
            'name' => 'Admin',
        ]);

        $techniker = Role::forceCreate([
            'id' => 10,
            'name' => 'Techniker',
        ]);

        $customerViewAny = Role::forceCreate([
            'name' => 'general_read',
            'description' => 'Standard Kunde lesen',
        ]);

        $customerDelete = Role::forceCreate([
            'name' => 'general_full',
            'description' => 'Standard Kunde Vollzugriff',
        ]);

        // Permissions
        $models = config('custom.permissions');

        $permissions = [
            'viewAny' => 'sehen',
            'create' => 'erstellen',
            'update' => 'bearbeiten',
            'delete' => 'löschen',
        ];

        foreach ($models as $model) {
            foreach ($permissions as $p => $pn) {
                ${strtolower($model).'_'.$p} = Permission::forceCreate([
                    'name' => strtolower($model).'_'.$p,
                    'description' => $model.' '.$pn,
                ]);
            }
        }

        $see_hidden = Permission::forceCreate([
            'name' => 'see_hidden',
            'description' => 'Verstecke Objekte sehen',
        ]);

        $create_pdf = Permission::forceCreate([
            'name' => 'create_pdf',
            'description' => 'PDF erstellen',
        ]);

        // Ein Recht je Admin-Menuepunkt, dazu die Fernwartungs-Suche. Vorher
        // hingen beide Bereiche an einer festen Rollen-Id.
        //
        // Vorhandene wiederverwenden statt anlegen: Dieselben Rechte legt auch
        // die Migration an, damit bestehende Installationen sie bekommen. Der
        // Deploy laeuft "migrate:fresh --seed" - erst die Migration, dann
        // dieser Seeder - und ein zweites forceCreate brach am UNIQUE-Index
        // auf permissions.name ab. Beide Wege muessen fuer sich stimmen und
        // sich gegenseitig vertragen.
        foreach (array_merge(config('custom.admin_permissions'), config('custom.extra_permissions')) as $name => $beschreibung) {
            $recht = Permission::where('name', $name)->first() ?? new Permission;
            $recht->forceFill(['name' => $name, 'description' => $beschreibung])->save();
        }

        // PermissionRole

        // admin
        $permissions = Permission::all();
        foreach ($permissions as $permission) {
            $admin->assignPermission($permission);
        }

        // techniker
        $permissions = Permission::all();
        foreach ($permissions as $permission) {
            $techniker->assignPermission($permission);
        }

        // standard kunde lesen
        $permissions = Permission::getviewAny()->get();
        foreach ($permissions as $permission) {
            $customerViewAny->assignPermission($permission);
        }

        // standard kunde lesen schreiben
        $permissions = Permission::getviewAny()->get();
        foreach ($permissions as $permission) {
            $customerDelete->assignPermission($permission);
        }

        $permissions = Permission::getcreate()->get();
        foreach ($permissions as $permission) {
            $customerDelete->assignPermission($permission);
        }

        $permissions = Permission::getupdate()->get();
        foreach ($permissions as $permission) {
            $customerDelete->assignPermission($permission);
        }

        $permissions = Permission::getdelete()->get();
        foreach ($permissions as $permission) {
            $customerDelete->assignPermission($permission);
        }

    }
}
