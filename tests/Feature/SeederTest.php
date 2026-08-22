<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionRoleSeeder;

test('der Rechte-Seeder laeuft auf einer frisch migrierten Datenbank', function () {
    // Genau der Ablauf des Deploys: "migrate:fresh --seed". Die Migration hat
    // die Admin-Rechte da schon angelegt - ein zweites Anlegen im Seeder brach
    // am UNIQUE-Index ab und der Deploy blieb stehen, mitten im Befuellen.
    expect(Permission::where('name', 'admin_customer')->exists())->toBeTrue();

    $this->seed(PermissionRoleSeeder::class);

    expect(Permission::where('name', 'admin_customer')->count())->toBe(1);
    expect(Role::find(Role::IS_ADMIN))->not->toBeNull();
});

test('nach dem Seeder gibt es jedes Admin-Recht genau einmal', function () {
    $this->seed(PermissionRoleSeeder::class);

    // Nicht nur "kein Absturz", sondern auch "kein Doppel": Zwei Zeilen mit
    // demselben Namen liessen sich in der Rollenverwaltung getrennt ankreuzen,
    // und welche das Gate trifft, waere Zufall.
    foreach (array_keys(array_merge(config('custom.admin_permissions'), config('custom.extra_permissions'))) as $name) {
        expect(Permission::where('name', $name)->count())->toBe(1, "Recht {$name} doppelt");
    }
});

test('nach dem Seeder hat die Admin-Rolle alle Rechte', function () {
    $this->seed(PermissionRoleSeeder::class);

    $admin = Role::find(Role::IS_ADMIN);

    // Die Migration weist die neuen Rechte nur zu, wenn die Rolle schon da ist.
    // Bei einer frischen Datenbank entsteht sie erst im Seeder - der muss sie
    // deshalb selbst mitgeben.
    foreach (array_keys(config('custom.admin_permissions')) as $name) {
        expect($admin->permissions()->where('name', $name)->exists())
            ->toBeTrue("Admin fehlt das Recht {$name}");
    }
});
