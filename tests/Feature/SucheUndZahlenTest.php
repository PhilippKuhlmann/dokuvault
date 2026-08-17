<?php

use App\Livewire\GlobalSearch;
use App\Models\Customer;
use App\Models\OperatingSystem;
use App\Models\Rack;
use App\Models\Role;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Zwei Engstellen, gemessen an 10 Millionen Datensaetzen: Die globale Suche
 * brauchte 2,8 Sekunden allein fuer die AD-Benutzer, das Admin-Dashboard 3,8
 * Sekunden fuer seine Zaehler.
 */
test('kleine Tabellen werden weiter mitten im Wort durchsucht', function () {
    $this->actingAs(userWithPermissions(['rack_viewAny']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    Rack::factory()->create(['customer_id' => $customer->id, 'site_id' => $site->id, 'name' => 'Rack HH-01']);

    $muster = [];
    DB::listen(function ($a) use (&$muster) {
        if (str_contains($a->sql, 'racks')) {
            foreach ($a->bindings as $b) {
                if (is_string($b) && str_contains($b, '%')) {
                    $muster[] = $b;
                }
            }
        }
    });

    // Das Rack heisst "Rack HH-01" und wird als "HH-01" gesucht. Eine reine
    // Praefix-Suche haette das verloren - genauso wie die Dose "EG 2.14", die
    // als "2.14" gesucht wird. Bei diesen Tabellengroessen ist ein
    // Tabellendurchlauf billiger als der Verlust an Treffern.
    Livewire::test(GlobalSearch::class)->set('search', 'HH-01')->assertSee('Rack HH-01');

    expect(collect($muster)->filter(fn ($b) => str_starts_with($b, '%')))->not->toBeEmpty();
});

test('die Massentabellen stehen in der Praefix-Liste', function () {
    // Die Zuordnung selbst ist die Entscheidung: 4 Mio AD-Benutzer, 2 Mio
    // Computer, 1 Mio VMs - dort kostete "%begriff%" Sekunden.
    $spiegel = new ReflectionClass(GlobalSearch::class);

    expect($spiegel->getConstant('MASSENHAFT'))
        ->toContain('aduser')->toContain('computer')->toContain('vm');
});

test('die Suche findet weiterhin ueber alle Felder am Wortanfang', function () {
    $this->actingAs(userWithPermissions(['server_viewAny']));

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    $os = OperatingSystem::factory()->create(['name' => 'Windows Server 2022']);
    $server = Server::factory()->create([
        'customer_id' => $customer->id, 'site_id' => $site->id,
        'operating_system_id' => $os->id, 'name' => 'HV-NW-01', 'serialNumber' => 'JK7X2M3',
    ]);
    $server->ipAddresses()->create(['customer_id' => $customer->id, 'address' => '10.20.10.11']);

    foreach (['HV-NW', 'JK7X', '10.20.10'] as $begriff) {
        Livewire::test(GlobalSearch::class)
            ->set('search', $begriff)
            ->assertSee('HV-NW-01');
    }
});

test('das Admin-Dashboard zaehlt nicht bei jedem Aufruf', function () {
    Cache::forget('admin.zahlen');

    $rolle = Role::factory()->create(['id' => Role::IS_ADMIN]);
    $this->actingAs(User::factory()->create(['role_id' => $rolle->id]));

    Customer::factory()->count(3)->create();

    $this->get('/admin')->assertOk();

    // Zweiter Aufruf: Die Zaehler kommen aus dem Zwischenspeicher, es darf
    // keine COUNT-Abfrage auf die grossen Tabellen mehr geben. Bei 10 Mio
    // Datensaetzen waren das 3,8 Sekunden pro Seitenaufruf.
    $zaehlAbfragen = 0;
    DB::listen(function ($a) use (&$zaehlAbfragen) {
        if (str_contains(strtolower($a->sql), 'count(*)') && str_contains($a->sql, 'ad_users')) {
            $zaehlAbfragen++;
        }
    });

    $this->get('/admin')->assertOk();

    expect($zaehlAbfragen)->toBe(0);
});
