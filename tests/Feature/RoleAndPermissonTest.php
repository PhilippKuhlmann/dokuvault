<?php

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

// Permissions
$models = [
    'Site',
    'ContactPerson',
    'Server',
    'VM',
    'NAS',
    'SecurepointUTM',
    'Router',
    'Network',
    'NetworkSwitch',
    'Wifi',
    'Accesspoint',
    'Computer',
    'IoTDevice',
    'Machine',
    'OtherClient',
    'Printer',
    'ADDomain',
    'ADUser',
    'ADGroup',
    'Phonesystem',
    'Phone',
    'DECT',
    'LoginGeneral',
    'LoginWebsite',
    'SecurepointUMA',
    'Mailbox',
    'Recorder',
    'Camera',
    'LicenseSoftware',
    'LicenseWindows',
    'LicenseAccess',
    'FTPServer',
    'DynDNS',
];

// view
foreach ($models as $model) {
    $model = strtolower($model);

    test('user with permission '.$model.'_viewAny can acceess the page', function () use ($model) {
        $permission = Permission::factory()->create(['name' => $model.'_viewAny']);
        $role = Role::factory()->create()->assignPermission($permission);

        $user = User::factory()->create(['role_id' => $role->id]);
        $customer = Customer::factory()->create();

        $this->actingAs($user)->get(route($model.'.index', $customer))
            ->assertStatus(200);
    });
}

foreach ($models as $model) {
    $model = strtolower($model);

    test('user without permission '.$model.'_viewAny cannot acceess the page', function () use ($model) {
        $role = Role::factory()->create();

        $user = User::factory()->create(['role_id' => $role->id]);
        $customer = Customer::factory()->create();

        $this->actingAs($user)->get(route($model.'.index', $customer))
            ->assertStatus(403);
    });
}

// create
// foreach ($models as $model) {
//     $model = strtolower($model);

//     test('user with permission '.$model.'_create can acceess the page', function () use ($model) {
//         $permission = Permission::factory()->create(['name' => $model.'_create',]);
//         $role = Role::factory()->create()->assignPermission($permission);

//         $user = User::factory()->create(['role_id' => $role->id,]);
//         $customer = Customer::factory()->create();

//         $this->actingAs($user)->get(route($model.'.create', $customer))
//             ->assertStatus(200);
//     });
// }

// foreach ($models as $model) {
//     $model = strtolower($model);

//     test('user without permission '.$model.'_create cannot acceess the page', function () use ($model) {
//         $role = Role::factory()->create();

//         $user = User::factory()->create(['role_id' => $role->id,]);
//         $customer = Customer::factory()->create();

//         $this->actingAs($user)->get(route($model.'.create', $customer))
//             ->assertStatus(403);
//     });
// }
