<?php

use App\Livewire\ObjektListe;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;

function clusterUmgebung(): array
{
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $site];
}

function serverFuer(Customer $customer, Site $site, string $name, ?Cluster $cluster = null): Server
{
    // Betriebssystem ausdruecklich: Die ServerFactory wuerfelt sonst eine
    // Id zwischen 1 und 10, ohne dass es die Zeile geben muss - der
    // Fremdschluessel schlaegt dann je nach Wurf fehl.
    return Server::factory()->create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'operating_system_id' => OperatingSystem::factory()->create(['name' => 'Proxmox VE 9'])->id,
        'name' => $name,
        'cluster_id' => $cluster?->id,
    ]);
}

test('ein Cluster laesst sich anlegen und landet beim Kunden', function () {
    [$customer, $site] = clusterUmgebung();
    $this->actingAs(userWithPermissions(['cluster_create']));

    imModal('cluster', $customer, [
        'site_id' => $site->id,
        'name' => 'PVE-Cluster',
        'type' => 'ceph',
        'note' => 'Drei Knoten',
    ])->assertHasNoErrors();

    $cluster = Cluster::where('name', 'PVE-Cluster')->first();
    expect($cluster->customer_id)->toBe($customer->id);
    expect($cluster->type)->toBe('ceph');
    // Ausgeschrieben fuer die Anzeige, nicht der rohe Schluessel.
    expect($cluster->typBezeichnung())->toBe('Ceph (verteilter Speicher)');
});

test('eine unbekannte Art wird abgelehnt', function () {
    [$customer, $site] = clusterUmgebung();
    $this->actingAs(userWithPermissions(['cluster_create']));

    // Sonst liesse sich ueber ein gefaelschtes Formular ein beliebiger Wert
    // hineinschreiben, den keine Anzeige mehr aufloest.
    imModal('cluster', $customer, [
        'site_id' => $site->id, 'name' => 'Krumm', 'type' => 'erfunden',
    ])->assertHasErrors('form.type');
});

test('ein Cluster eines fremden Kunden laesst sich am Server nicht auswaehlen (IDOR)', function () {
    [$customer, $site] = clusterUmgebung();
    [$fremderKunde, $fremderStandort] = clusterUmgebung();
    $this->actingAs(userWithPermissions(['server_update']));

    $fremderCluster = Cluster::factory()->create([
        'customer_id' => $fremderKunde->id, 'site_id' => $fremderStandort->id,
    ]);
    $server = serverFuer($customer, $site, 'pve01');

    imModalBearbeiten('server', $customer, $server, [
        'site_id' => $site->id, 'name' => 'pve01', 'cluster_id' => $fremderCluster->id,
        'form_factor' => 'tower', 'operating_system_id' => $server->operating_system_id,
    ])->assertHasErrors('form.cluster_id');

    expect($server->fresh()->cluster_id)->toBeNull();
});

test('ein geloeschter Cluster nimmt seine Server nicht mit', function () {
    [$customer, $site] = clusterUmgebung();
    $this->actingAs(userWithPermissions(['cluster_update', 'cluster_delete']));
    $cluster = Cluster::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);
    $server = serverFuer($customer, $site, 'pve01', $cluster);

    imModalLoeschen('cluster', $customer, $cluster);

    // Der Server ist ein eigenes Geraet - er verliert nur die Zugehoerigkeit.
    expect($server->fresh())->not->toBeNull();
    expect($server->fresh()->cluster_id)->toBeNull();
    expect(Cluster::find($cluster->id))->toBeNull();
});

test('die Liste zeigt den Cluster mit Art und Knoten', function () {
    [$customer, $site] = clusterUmgebung();
    $this->actingAs(userWithPermissions(['cluster_viewAny']));
    $cluster = Cluster::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'name' => 'PVE-Cluster', 'type' => 'ceph',
    ]);
    serverFuer($customer, $site, 'pve01', $cluster);

    // Die Liste ist Livewire (objekt-liste), wie bei den Servern.
    Livewire::test(ObjektListe::class, ['typ' => 'cluster', 'customer' => $customer])
        ->assertSee('PVE-Cluster')
        ->assertSee('Ceph (verteilter Speicher)')
        ->assertSee('pve01');
});

test('die Cluster-Liste laesst sich durchsuchen', function () {
    [$customer, $site] = clusterUmgebung();
    $this->actingAs(userWithPermissions(['cluster_viewAny']));
    Cluster::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'PVE-Cluster']);
    Cluster::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'SQL-Cluster']);

    Livewire::test(ObjektListe::class, ['typ' => 'cluster', 'customer' => $customer])
        ->set('search', 'PVE')
        ->assertSee('PVE-Cluster')
        ->assertDontSee('SQL-Cluster');
});

test('ohne Recht bleibt der Cluster-Bereich zu', function () {
    [$customer, $site] = clusterUmgebung();
    $this->actingAs(userWithPermissions([]));

    $this->get(route('cluster.index', $customer))->assertForbidden();
});

test('am Server selbst laesst sich der Cluster ebenfalls setzen', function () {
    [$customer, $site] = clusterUmgebung();
    $this->actingAs(userWithPermissions(['server_update']));
    $cluster = Cluster::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id]);
    $server = serverFuer($customer, $site, 'pve01');

    imModalBearbeiten('server', $customer, $server, [
        'site_id' => $site->id, 'name' => 'pve01', 'cluster_id' => $cluster->id,
        'form_factor' => 'tower', 'operating_system_id' => $server->operating_system_id,
    ])->assertHasNoErrors();

    expect($server->fresh()->cluster_id)->toBe($cluster->id);
});
