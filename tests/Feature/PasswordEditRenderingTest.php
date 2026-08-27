<?php

use App\Livewire\ObjektFormular;
use App\Models\Customer;
use App\Models\LicenseSoftware;
use App\Models\OperatingSystem;
use App\Models\Router;
use App\Models\Server;
use App\Models\Site;
use Livewire\Livewire;

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

    // Das Modal bindet ueber wire:model, der Wert steht also im Zustand der
    // Komponente und nicht in einem value-Attribut. Genau dort muss er
    // unveraendert ankommen - eine zusaetzliche Maskierung faellt hier auf.
    $formular = Livewire::test(ObjektFormular::class, ['typ' => 'router', 'customer' => $customer])
        ->call('bearbeiten', 'router', $router->id);

    expect($formular->get('form')['password'])->toBe($password);

    // Speichern ohne Änderung -> Wert bleibt exakt gleich
    imModalBearbeiten('router', $customer, $router, [
        'site_id' => $site->id,
        'name' => 'RTR-Test',
        'username' => 'admin',
        'password' => $password,
        'ip' => '10.0.0.1',
        'port' => '443',
    ])->assertHasNoErrors();

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

    expect(str_contains(modalHtml('server', $customer, $server->id), '<script>alert(1)</script>'))
        ->toBeFalse('Der Payload bricht aus dem Attribut aus.');
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
