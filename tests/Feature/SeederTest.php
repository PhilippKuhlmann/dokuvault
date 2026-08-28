<?php

use App\Models\Cluster;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Server;
use App\Models\VM;
use Database\Seeders\LocalDatabaseSeeder;
use Database\Seeders\PermissionRoleSeeder;

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

test('der Demo-Datensatz enthaelt einen Proxmox-Cluster mit drei Knoten und VMs darauf', function () {
    // Der Cluster ist der Fall, fuer den es die Cluster-Doku gibt - ohne
    // Beispiel sieht man in der Demo nur leere Listen. Drei Knoten, weil ein
    // Ceph-Quorum genau daran haengt.
    $this->seed(LocalDatabaseSeeder::class);

    $cluster = Cluster::where('name', 'PVE-Cluster HH')->sole();
    expect($cluster->type)->toBe('ceph');
    expect(config('custom.cluster_types'))->toHaveKey($cluster->type);

    expect(Server::where('cluster_id', $cluster->id)->count())->toBe(3);

    // Am Cluster, nicht an einem Knoten: Im HA-Cluster wandert die VM zwischen
    // ihnen, ein fester Host waere nach der ersten Migration falsch.
    $vms = VM::where('cluster_id', $cluster->id)->get();
    expect($vms->count())->toBeGreaterThanOrEqual(2);
    expect($vms->whereNotNull('server_id'))->toBeEmpty();

    // Der Standort kommt vom Cluster (VM::booted), nicht aus dem Seeder-Array.
    expect($vms->pluck('site_id')->unique()->all())->toBe([$cluster->site_id]);
});
