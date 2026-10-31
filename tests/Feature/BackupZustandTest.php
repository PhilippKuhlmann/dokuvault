<?php

use App\Support\BackupHealth;
use App\Support\BackupSettings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;

beforeEach(function () {
    Storage::fake('local');
});

function mitArchivUndZiel(): void
{
    BackupSettings::speichern([
        'backup_geheim_archivpasswort' => 'archiv-passwort',
        'backup_ziel_art' => 'sftp', 'backup_ziel_host' => '127.0.0.1', 'backup_ziel_benutzer' => 'backup',
    ]);
}

test('without any backup the state is red', function () {
    expect(BackupHealth::zustand())->stufe->toBe('fehler')->text->toContain('noch keine');
});

test('a recent backup without archive password or target is amber', function () {
    BackupHealth::merken('local', null);
    expect(BackupHealth::zustand())->stufe->toBe('warnung')->text->toContain('APP_KEY');

    BackupSettings::speichern(['backup_geheim_archivpasswort' => 'archiv-passwort']);
    expect(BackupHealth::zustand())->stufe->toBe('warnung')->text->toContain('nur auf diesem Server');
});

test('local and external copies with archive password are green', function () {
    mitArchivUndZiel();
    BackupHealth::merken('local', null);
    BackupHealth::merken(BackupSettings::ZIEL_DISK, null);

    expect(BackupHealth::zustand())->stufe->toBe('ok')->letzte->not->toBeNull();
});

test('a failed external copy after the last success is red', function () {
    mitArchivUndZiel();
    BackupHealth::merken('local', null);
    BackupHealth::merken(BackupSettings::ZIEL_DISK, null);
    $this->travel(1)->minutes();
    BackupHealth::merken(BackupSettings::ZIEL_DISK, 'Could not connect');

    expect(BackupHealth::zustand())->stufe->toBe('fehler')->text->toBe('Externes Ziel: Could not connect');

    // The next good night clears it.
    $this->travel(1)->minutes();
    BackupHealth::merken(BackupSettings::ZIEL_DISK, null);
    expect(BackupHealth::zustand())->stufe->toBe('ok');
});

test('a failed run is noted through the package event and shown red', function () {
    mitArchivUndZiel();
    BackupHealth::merken('local', null);
    $this->travel(1)->minutes();

    event(new BackupHasFailed(new Exception('mysqldump: Got error')));

    expect(BackupHealth::zustand())->stufe->toBe('fehler')->text->toContain('mysqldump: Got error');
});

test('a backup older than a day is red, a switched off one amber', function () {
    mitArchivUndZiel();
    BackupHealth::merken('local', null);
    BackupHealth::merken(BackupSettings::ZIEL_DISK, null);
    $this->travel(BackupHealth::HOECHSTALTER_STUNDEN + 1)->hours();

    expect(BackupHealth::zustand())->stufe->toBe('fehler')->text->toContain('alt');

    BackupSettings::speichern(['backup_aktiv' => '0']);
    expect(BackupHealth::zustand())->stufe->toBe('warnung')->text->toContain('ausgeschaltet');
});

test('backups from before the tracking count by their file date', function () {
    Storage::disk('local')->put(config('backup.backup.name').'/2026-10-06-01-30-00.zip', 'zip');

    expect(BackupHealth::zustand()['letzte'])->not->toBeNull();
});

test('the line renders with its state', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));
    BackupHealth::merken('local', null);

    expect(Blade::render('<x-backup-zustand />'))
        ->toContain('Sicherung von DokuVault')->toContain('APP_KEY')->toContain('bg-amber-500');
});
