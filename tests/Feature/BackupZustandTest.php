<?php

use App\Support\BackupEinstellungen;
use App\Support\BackupZustand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;

beforeEach(function () {
    Storage::fake('local');
});

function mitArchivUndZiel(): void
{
    BackupEinstellungen::speichern([
        'backup_geheim_archivpasswort' => 'archiv-passwort',
        'backup_ziel_art' => 'sftp', 'backup_ziel_host' => '127.0.0.1', 'backup_ziel_benutzer' => 'backup',
    ]);
}

test('without any backup the state is red', function () {
    expect(BackupZustand::zustand())->stufe->toBe('fehler')->text->toContain('noch keine');
});

test('a recent backup without archive password or target is amber', function () {
    BackupZustand::merken('local', null);
    expect(BackupZustand::zustand())->stufe->toBe('warnung')->text->toContain('APP_KEY');

    BackupEinstellungen::speichern(['backup_geheim_archivpasswort' => 'archiv-passwort']);
    expect(BackupZustand::zustand())->stufe->toBe('warnung')->text->toContain('nur auf diesem Server');
});

test('local and external copies with archive password are green', function () {
    mitArchivUndZiel();
    BackupZustand::merken('local', null);
    BackupZustand::merken(BackupEinstellungen::ZIEL_DISK, null);

    expect(BackupZustand::zustand())->stufe->toBe('ok')->letzte->not->toBeNull();
});

test('a failed external copy after the last success is red', function () {
    mitArchivUndZiel();
    BackupZustand::merken('local', null);
    BackupZustand::merken(BackupEinstellungen::ZIEL_DISK, null);
    $this->travel(1)->minutes();
    BackupZustand::merken(BackupEinstellungen::ZIEL_DISK, 'Could not connect');

    expect(BackupZustand::zustand())->stufe->toBe('fehler')->text->toBe('Externes Ziel: Could not connect');

    // The next good night clears it.
    $this->travel(1)->minutes();
    BackupZustand::merken(BackupEinstellungen::ZIEL_DISK, null);
    expect(BackupZustand::zustand())->stufe->toBe('ok');
});

test('a failed run is noted through the package event and shown red', function () {
    mitArchivUndZiel();
    BackupZustand::merken('local', null);
    $this->travel(1)->minutes();

    event(new BackupHasFailed(new Exception('mysqldump: Got error')));

    expect(BackupZustand::zustand())->stufe->toBe('fehler')->text->toContain('mysqldump: Got error');
});

test('a backup older than a day is red, a switched off one amber', function () {
    mitArchivUndZiel();
    BackupZustand::merken('local', null);
    BackupZustand::merken(BackupEinstellungen::ZIEL_DISK, null);
    $this->travel(BackupZustand::HOECHSTALTER_STUNDEN + 1)->hours();

    expect(BackupZustand::zustand())->stufe->toBe('fehler')->text->toContain('alt');

    BackupEinstellungen::speichern(['backup_aktiv' => '0']);
    expect(BackupZustand::zustand())->stufe->toBe('warnung')->text->toContain('ausgeschaltet');
});

test('backups from before the tracking count by their file date', function () {
    Storage::disk('local')->put(config('backup.backup.name').'/2026-10-06-01-30-00.zip', 'zip');

    expect(BackupZustand::zustand()['letzte'])->not->toBeNull();
});

test('the line renders with its state', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));
    BackupZustand::merken('local', null);

    expect(Blade::render('<x-backup-zustand />'))
        ->toContain('Sicherung von DokuVault')->toContain('APP_KEY')->toContain('bg-amber-500');
});
