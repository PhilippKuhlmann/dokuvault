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
        foreach (array_merge(config('custom.admin_permissions'), config('custom.extra_permissions')) as $name => $beschreibung) {
            Permission::forceCreate(['name' => $name, 'description' => $beschreibung]);
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
