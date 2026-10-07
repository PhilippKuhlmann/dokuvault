<?php

use App\Console\Kernel;
use App\Livewire\AdminAgentenEinstellungen;
use App\Livewire\AdminFristen;
use App\Livewire\AdminSicherheit;
use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Site;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;

// Values that were fixed in code or config and are now under
// Admin -> Einstellungen: agent defaults, statistics retention, the
// password brake, and the backup time in the set time zone.

test('without settings the old defaults hold, out of range falls back', function () {
    expect(Setting::agentIntervall())->toBe(60)
        ->and(Setting::agentStillStunden())->toBe(3)
        ->and(Setting::agentTokenTage())->toBe(365)
        ->and(Setting::statistikApiTage())->toBe(90)
        ->and(Setting::statistikMonate())->toBe(24)
        ->and(Setting::kennwortAbrufe())->toBe(30);

    Setting::setzen(Setting::AGENT_STILL_STUNDEN, 0);
    Setting::setzen(Setting::AGENT_INTERVALL, 45);
    expect(Setting::agentStillStunden())->toBe(3)->and(Setting::agentIntervall())->toBe(60);
});

test('the agent page stores the defaults and they take effect', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminAgentenEinstellungen::class)
        ->set('intervall', 30)->assertHasNoErrors()
        ->set('stillStunden', 12)->assertHasNoErrors()
        ->set('tokenMaxTage', 90)->assertHasNoErrors()
        // The default follows the lower maximum.
        ->assertSet('tokenTage', 90)
        ->set('tokenTage', 120)->assertHasErrors('tokenTage');

    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);
    [$token] = AgentToken::generateFor($customer, $site);
    $installation = new AgentInstallation;
    $installation->forceFill([
        'customer_id' => $customer->id, 'agent_token_id' => $token->id,
        'machine_id' => 'm1', 'hostname' => 'SRV', 'last_seen_at' => now()->subHours(5),
    ]);

    // 5 hours silent: still within 12 hours; interval from the default.
    expect($installation->isStale())->toBeFalse()
        ->and($installation->intervalMinutes())->toBe(30)
        ->and(Setting::agentTokenTage())->toBe(90);
});

test('a token beyond the maximum validity is refused', function () {
    Setting::setzen(Setting::AGENT_TOKEN_MAX_TAGE, 30);
    $this->actingAs(userWithPermissions(['agent_manage']));
    $customer = Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    $this->post(route('agent.store', $customer), ['site_id' => $site->id, 'expires_at' => now()->addDays(60)->format('Y-m-d')])
        ->assertSessionHasErrors('expires_at');
    $this->post(route('agent.store', $customer), ['site_id' => $site->id, 'expires_at' => now()->addDays(20)->format('Y-m-d')])
        ->assertSessionHasNoErrors();
});

test('statistics retention and the password brake are stored', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminFristen::class)
        ->set('statistikApiTage', 365)->assertHasNoErrors()
        ->set('statistikMonate', 0)->assertHasErrors('statistikMonate');
    Livewire::test(AdminSicherheit::class)
        ->set('kennwortAbrufe', 5)->assertHasNoErrors();

    expect(Setting::statistikApiTage())->toBe(365)
        ->and(Setting::statistikMonate())->toBe(24)
        ->and(Setting::kennwortAbrufe())->toBe(5);
});

test('the settings pages need the settings right', function () {
    $this->actingAs(userWithPermissions(['admin_statistik']));

    Livewire::test(AdminAgentenEinstellungen::class)->assertForbidden();
});

test('the backup runs at its time in the set time zone', function () {
    Setting::setzen(Setting::APP_TIMEZONE, 'Europe/Berlin');

    // The schedule as the kernel builds it now - not the one from boot,
    // before the setting existed.
    $schedule = new Schedule;
    (fn () => $this->schedule($schedule))->call(app(Kernel::class));
    $ereignis = collect($schedule->events())->first(fn ($e) => str_contains($e->command ?? '', 'backup:run'));

    expect($ereignis->expression)->toBe('30 1 * * *')
        ->and((string) $ereignis->timezone)->toBe('Europe/Berlin');
});
