<?php

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Server;
use App\Models\Service;
use App\Models\Site;
use Database\Seeders\ServiceSeeder;

test('a new installation gets the standard service catalog', function () {
    $this->seed(ServiceSeeder::class);

    expect(Service::pluck('name')->sort()->values()->all())
        ->toBe(collect(array_keys(ServiceSeeder::KATALOG))->sort()->values()->all());
});

test('services that already exist are kept, whatever their spelling', function () {
    Service::create(['name' => 'docker', 'color' => '#000000', 'description' => 'eigener Text']);

    $this->seed(ServiceSeeder::class);
    $this->seed(ServiceSeeder::class);

    expect(Service::whereRaw('LOWER(name) = ?', ['docker'])->count())->toBe(1)
        ->and(Service::where('name', 'docker')->sole()->description)->toBe('eigener Text')
        ->and(Service::count())->toBe(count(ServiceSeeder::KATALOG));
});

test('the agents find the standard services regardless of case and spelling', function () {
    $this->seed(ServiceSeeder::class);
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site);

    // Linux: docker -> Docker, smbd -> FS.
    $this->withToken($plain)->postJson('/api/agent/linux-server', ['server' => [
        'identifier' => 'linux/abc', 'hostname' => 'srv-files', 'virtual' => false,
        'services' => ['docker', 'smbd'],
    ]])->assertOk();

    expect(Server::where('customer_id', $customer->id)->sole()->getRawOriginal('services'))->toBe('Docker,FS');
});

test('nginx on a Linux server becomes Web in the standard catalog', function () {
    $this->seed(ServiceSeeder::class);
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [, $plain] = AgentToken::generateFor($customer, $site);

    $this->withToken($plain)->postJson('/api/agent/linux-server', ['server' => [
        'identifier' => 'linux/web', 'hostname' => 'srv-web', 'virtual' => false, 'services' => ['nginx'],
    ]])->assertOk();

    expect(Server::where('customer_id', $customer->id)->sole()->getRawOriginal('services'))->toBe('Web');
});
