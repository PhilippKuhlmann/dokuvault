<?php

use App\Livewire\AdminBackups;
use App\Models\Backup;
use App\Models\Customer;
use App\Models\Setting;
use App\Support\BackupRunDays;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * The run grid: one square per day instead of one per run. Counted in
 * runs, a job that silently stopped running stayed green - counted in
 * days, the gap shows.
 */

function gridBackup(array $runs, ?Customer $customer = null): Backup
{
    $backup = Backup::forceCreate([
        'customer_id' => ($customer ?? Customer::factory()->create())->id,
        'name' => 'Nachtsicherung', 'software' => 'Veeam', 'agent_identifier' => 'veeam/'.uniqid(),
        'last_status' => $runs ? end($runs)[1] : null,
    ]);

    foreach ($runs as [$daysAgo, $status, $hour]) {
        $backup->runs()->create([
            'status' => $status,
            'finished_at' => Carbon::now(config('app.timezone'))->subDays($daysAgo)->setTime($hour, 0)->utc(),
        ]);
    }

    return $backup->load('recentRuns', 'firstRun');
}

beforeEach(function () {
    // Midday, so "today at 02:00" is in the past.
    Carbon::setTestNow(Carbon::parse('2026-11-22 12:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('one cell per day, oldest left, today right', function () {
    $cells = BackupRunDays::for(gridBackup([[3, 'ok', 2], [0, 'ok', 2]]), 7);

    expect($cells)->toHaveCount(7)
        ->and($cells[0]['date'])->toBe('2026-11-16')
        ->and($cells[6]['date'])->toBe('2026-11-22')
        ->and($cells[6]['state'])->toBe('ok');
});

test('a day without a run is a gap, a day before the first run is unknown', function () {
    $cells = BackupRunDays::for(gridBackup([[4, 'ok', 2], [1, 'ok', 2]]), 7);

    expect(array_column($cells, 'state'))->toBe(['unknown', 'unknown', 'ok', 'none', 'none', 'ok', 'none'])
        ->and($cells[3]['label'])->toBe('19.11.2026 · kein Lauf')
        ->and($cells[0]['label'])->toBe('16.11.2026 · noch nicht erfasst');
});

test('the worst run of a day counts, the label says how many there were', function () {
    $cells = BackupRunDays::for(gridBackup([[0, 'ok', 1], [0, 'failed', 3], [0, 'warning', 5]]), 7);

    expect($cells[6]['state'])->toBe('failed')
        ->and($cells[6]['runs'])->toBe(3)
        ->and($cells[6]['label'])->toBe('22.11.2026 · Fehlgeschlagen (3 Läufe)');
});

test('a job that stopped running shows grey, not green', function () {
    // The last run three weeks ago was fine - the old squares said "all ok".
    $cells = BackupRunDays::for(gridBackup([[22, 'ok', 2], [21, 'ok', 2]]), 14);

    expect(collect($cells)->pluck('state')->unique()->values()->all())->toBe(['none']);
});

test('the admin overview shows the grid, the counts and the period', function () {
    $this->actingAs(userWithPermissions(['admin_backup']));
    $customer = Customer::factory()->create();
    gridBackup([[2, 'ok', 2], [1, 'failed', 2]], $customer);
    gridBackup([[1, 'ok', 2], [0, 'ok', 2]], $customer);

    Livewire::test(AdminBackups::class)
        ->assertSee('1 fehlgeschlagen')
        ->assertSee('0 mit Warnung')
        ->assertSee('1 erfolgreich')
        ->assertSee('Letzte 14 Tage')
        ->assertSee('21.11.2026 · Fehlgeschlagen')
        ->assertSee('1 / 2')
        ->assertSee('2 / 2')
        // The counts stay when only problems are listed.
        ->set('nurProbleme', true)
        ->assertSee('1 erfolgreich')
        ->assertDontSee('2 / 2');

    Setting::setzen(Setting::BACKUP_VERLAUF, 7);
    Livewire::test(AdminBackups::class)->assertSee('Letzte 7 Tage');
});

test('the customer card shows the same grid', function () {
    $this->actingAs(userWithPermissions(['backup_viewAny']));
    $backup = gridBackup([[1, 'warning', 2]]);

    $this->get(route('backup.index', $backup->customer))->assertOk()
        ->assertSee('Letzte 14 Tage')
        ->assertSee('21.11.2026 · Mit Warnungen')
        ->assertSee('20.11.2026 · noch nicht erfasst');
});

test('a reported backup without any run says so', function () {
    $this->actingAs(userWithPermissions(['backup_viewAny']));
    $backup = gridBackup([]);

    $this->get(route('backup.index', $backup->customer))->assertOk()
        ->assertSee('noch keine Läufe gemeldet');
});
