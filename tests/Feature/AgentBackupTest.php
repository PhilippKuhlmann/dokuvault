<?php

use App\Models\AgentToken;
use App\Models\Backup;
use App\Models\Customer;
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
