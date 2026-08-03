<?php

use App\Models\Customer;
use App\Models\LicenseSoftware;
use App\Models\OperatingSystem;
use App\Models\Router;
use App\Models\Server;
use App\Models\Site;

test('Passwort mit Sonderzeichen übersteht den Edit-Roundtrip unverändert', function () {
    $this->actingAs(userWithPermissions(['router_update', 'router_viewAny']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $password = 'a&b"<c>\'d';

    $router = Router::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'RTR-Test',
        'username' => 'admin',
        'password' => $password,
        'ip' => '10.0.0.1',
        'port' => '443',
    ]);

    // Edit-Seite: genau 1x escaped im value-Attribut, kein Doppel-Escaping
    $response = $this->get("/{$customer->slug}/router/{$router->id}/edit");
    $response->assertStatus(200);
    $response->assertSee('value="'.e($password).'"', false);
    $response->assertDontSee('&amp;amp;', false);

    // Speichern ohne Änderung -> Wert bleibt exakt gleich
    $this->patch("/{$customer->slug}/router/{$router->id}", [
        'site_id' => $site->id,
        'name' => 'RTR-Test',
        'username' => 'admin',
        'password' => $password,
        'ip' => '10.0.0.1',
        'port' => '443',
    ])->assertSessionHasNoErrors();

    expect($router->fresh()->password)->toBe($password);
});

test('Script-Payload im Passwort bricht nicht aus dem Attribut aus (XSS)', function () {
    $this->actingAs(userWithPermissions(['server_update']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $payload = '"><script>alert(1)</script>';

    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);

    $server = Server::create([
        'customer_id' => $customer->id,
        'site_id' => $site->id,
        'name' => 'SRV-XSS',
        'operating_system_id' => $os->id,
        'bmcPassword' => $payload,
        'remotePassword' => $payload,
    ]);

    $this->get("/{$customer->slug}/server/{$server->id}/edit")
        ->assertStatus(200)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('Dateiname in Software-Lizenz-Liste wird escaped (Stored XSS)', function () {
    $this->actingAs(userWithPermissions(['licensesoftware_viewAny']));

    $customer = Customer::factory()->create();

    LicenseSoftware::create([
        'customer_id' => $customer->id,
        'name' => 'Testsoftware',
        'key' => 'AAAAA',
        'file_path' => 'files/x.pdf',
        'file_name' => '<script>alert(2)</script>',
    ]);

    $this->get("/{$customer->slug}/licensesoftware")
        ->assertStatus(200)
        ->assertDontSee('<script>alert(2)</script>', false);
});
