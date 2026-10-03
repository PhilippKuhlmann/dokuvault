<?php

use App\Livewire\AdminBackups;
use App\Models\AgentToken;
use App\Models\Backup;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Site;

/*
 * Backups reported by the agents: Veeam and Windows Server-Sicherung to
 * /api/agent/backup, Proxmox vzdump jobs with the Proxmox report.
 */

function backupToken(?Customer $customer = null): array
{
    $customer ??= Customer::factory()->create();
    $site = Site::factory()->create(['customer_id' => $customer->id]);

    return [$customer, AgentToken::generateFor($customer, $site, 'Backup', now()->addMonth())[1]];
}

function veeamJob(array $anders = []): array
{
    return array_merge([
        'identifier' => 'veeam/1111-2222',
        'name' => 'Nachtsicherung Server',
        'software' => 'Veeam Backup & Replication',
        'source' => 'SRV-DC01, SRV-FILE01',
        'destination' => 'Repo-NAS01',
        'schedule' => 'taeglich 22:00',
        'retention' => '14 Wiederherstellungspunkte',
        'last_status' => 'ok',
        'last_run_at' => '2026-10-02T22:41:10+02:00',
        'last_success' => '2026-10-02T22:41:10+02:00',
    ], $anders);
}

test('Veeam jobs are documented as backups with the outcome of the last run', function () {
    [$customer, $plain] = backupToken();

    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob()]])
        ->assertOk()
        ->assertJsonPath('backups_documented', 1);

    $backup = Backup::where('customer_id', $customer->id)->sole();
    expect($backup->name)->toBe('Nachtsicherung Server')
        ->and($backup->destination)->toBe('Repo-NAS01')
        ->and($backup->last_status)->toBe('ok')
        ->and($backup->last_run_at->format('Y-m-d'))->toBe('2026-10-02')
        ->and((string) $backup->last_success)->toStartWith('2026-10-02');
});

test('a second run updates the same backup and keeps notes and password', function () {
    [$customer, $plain] = backupToken();
    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob()]]);
    Backup::sole()->update(['notes' => 'Band wird freitags getauscht', 'password' => 'geheim']);

    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['last_status' => 'failed', 'last_success' => null])]]);

    $backup = Backup::sole();
    expect($backup->last_status)->toBe('failed')
        ->and((string) $backup->last_success)->toStartWith('2026-10-02')
        ->and($backup->notes)->toBe('Band wird freitags getauscht')
        ->and($backup->password)->toBe('geheim');
});

test('a backup documented by hand with the same name is adopted', function () {
    [$customer, $plain] = backupToken();
    $vonHand = Backup::create(['customer_id' => $customer->id, 'name' => 'nachtsicherung server', 'notes' => 'von Hand']);

    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob()]]);

    expect(Backup::where('customer_id', $customer->id)->count())->toBe(1)
        ->and($vonHand->fresh()->agent_identifier)->toBe('veeam/1111-2222')
        ->and($vonHand->fresh()->notes)->toBe('von Hand');
});

test('an unknown status is refused', function () {
    [, $plain] = backupToken();

    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['last_status' => 'kaputt'])]])
        ->assertUnprocessable();
});

test('the Proxmox report brings its vzdump jobs along', function () {
    [$customer, $plain] = backupToken();

    $payload = [
        'host' => ['identifier' => 'pve-machine-id', 'hostname' => 'pve01.firma.local', 'pve_version' => '8.2.4'],
        'guests' => [],
        'backups' => [[
            'identifier' => 'proxmox/cluster-a/backup-1a2b',
            'name' => 'Nachtsicherung alle VMs',
            'software' => 'Proxmox vzdump',
            'source' => 'alle Gaeste ausser 105',
            'destination' => 'pbs01',
            'schedule' => '21:00',
            'retention' => 'keep-daily=7,keep-weekly=4',
            'last_status' => 'warning',
            'last_run_at' => '2026-10-02T21:37:00+02:00',
            'last_success' => '',
        ]],
    ];

    $this->withToken($plain)->postJson('/api/agent/proxmox', $payload)
        ->assertOk()
        ->assertJsonPath('backups_documented', 1);

    $backup = Backup::where('customer_id', $customer->id)->sole();
    expect($backup->destination)->toBe('pbs01')
        ->and($backup->last_status)->toBe('warning')
        ->and($backup->last_success)->toBeNull();
});

test('the dashboard warns about failed backups and the card shows the status', function () {
    $this->actingAs(userWithPermissions(['see_hidden', 'backup_viewAny']));
    [$customer, $plain] = backupToken();
    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['last_status' => 'failed'])]]);

    $this->get(route('customer.dashboard', $customer))
        ->assertOk()
        ->assertViewHas('agentWarnings', fn ($w) => $w->pluck('name')->contains('Nachtsicherung Server'));

    $this->get(route('backup.index', $customer))
        ->assertOk()
        ->assertSee('Fehlgeschlagen');
});

test('reported runs build the history, each run once', function () {
    [$customer, $plain] = backupToken();
    $laeufe = [
        ['status' => 'ok', 'finished_at' => '2026-10-02T22:41:10+02:00'],
        ['status' => 'failed', 'finished_at' => '2026-10-01T22:40:00+02:00'],
    ];

    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['runs' => $laeufe])]])->assertOk();
    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['runs' => $laeufe])]])->assertOk();

    $backup = Backup::where('customer_id', $customer->id)->sole();
    // The two runs, the last run (same as the first) not doubled.
    expect($backup->runs()->count())->toBe(2)
        ->and($backup->runs->pluck('status')->all())->toBe(['ok', 'failed']);
});

test('without runs the last run alone builds up the history', function () {
    [$customer, $plain] = backupToken();

    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob()]]);
    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['last_status' => 'failed', 'last_run_at' => '2026-10-03T22:40:00+02:00'])]]);

    expect(Backup::where('customer_id', $customer->id)->sole()->runs->pluck('status')->all())->toBe(['failed', 'ok']);
});

test('the admin overview shows the last runs of every reported backup', function () {
    $this->actingAs(userWithPermissions(['admin_backup']));
    [$customer, $plain] = backupToken();
    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['runs' => [
        ['status' => 'failed', 'finished_at' => '2026-10-01T22:40:00+02:00'],
    ]])]]);
    Backup::create(['customer_id' => $customer->id, 'name' => 'Von Hand gepflegt']);

    $this->get(route('admin.backups'))
        ->assertOk()
        ->assertSee('Nachtsicherung Server')
        ->assertSee('1 / 2')
        // Only reported backups - a hand-entered one has no runs.
        ->assertDontSee('Von Hand gepflegt');
});

test('the number of runs is a setting and the page needs its right', function () {
    $this->actingAs(userWithPermissions(['admin_backup']));

    Livewire\Livewire::test(AdminBackups::class)
        ->assertSet('anzahl', 10)
        ->set('anzahl', 20);

    expect(Setting::backupVerlauf())->toBe(20);
});

test('without the right there is no backup overview', function () {
    $this->actingAs(userWithPermissions(['admin_activity']));

    $this->get(route('admin.backups'))->assertForbidden();
});

function laeufe(int $anzahl, string $status = 'ok'): array
{
    return collect(range(1, $anzahl))
        ->map(fn ($i) => ['status' => $status, 'finished_at' => now()->subDays($i)->toIso8601String()])
        ->all();
}

test('the customer sees the last runs on the backup card', function () {
    $this->actingAs(userWithPermissions(['backup_viewAny']));
    [$customer, $plain] = backupToken();
    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['runs' => laeufe(3, 'failed')])]]);

    $this->get(route('backup.index', $customer))
        ->assertOk()
        ->assertSee('Letzte Läufe')
        ->assertSee('Fehlgeschlagen');
});

test('only the configured number of runs is kept', function () {
    Setting::setzen(Setting::BACKUP_AUFBEWAHRUNG, 20);
    [$customer, $plain] = backupToken();

    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['runs' => laeufe(30)])]]);

    expect(Backup::where('customer_id', $customer->id)->sole()->runs()->count())->toBe(20);
});

test('lowering the retention in the admin area deletes old runs at once', function () {
    $this->actingAs(userWithPermissions(['admin_backup']));
    [$customer, $plain] = backupToken();
    $this->withToken($plain)->postJson('/api/agent/backup', ['jobs' => [veeamJob(['runs' => laeufe(40)])]]);
    $backup = Backup::where('customer_id', $customer->id)->sole();
    $neuester = $backup->runs()->first()->finished_at;

    Livewire\Livewire::test(AdminBackups::class)
        ->set('aufbewahrung', 15)
        ->assertHasNoErrors();

    expect(Setting::backupAufbewahrung())->toBe(15)
        ->and($backup->runs()->count())->toBe(15)
        ->and($backup->runs()->first()->finished_at->equalTo($neuester))->toBeTrue();
});

test('the retention cannot be lower than the runs shown', function () {
    $this->actingAs(userWithPermissions(['admin_backup']));

    Livewire\Livewire::test(AdminBackups::class)
        ->set('anzahl', 30)
        ->set('aufbewahrung', 20)
        ->assertHasErrors('aufbewahrung');

    expect(Setting::backupAufbewahrung())->toBeGreaterThanOrEqual(30);
});
