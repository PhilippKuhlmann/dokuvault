<?php

use App\Livewire\AdminBackupEinstellungen;
use App\Models\Setting;
use App\Support\BackupEinstellungen;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Backup\Events\BackupZipWasCreated;
use Spatie\Backup\Listeners\EncryptBackupArchive;

/*
 * Admin -> Einstellungen -> Backup: the backup of DokuVault itself.
 */

test('without settings the backup stays local at 01:30, without mails to nowhere', function () {
    BackupEinstellungen::anwenden();

    expect(config('backup.backup.destination.disks'))->toBe(['local'])
        ->and(BackupEinstellungen::uhrzeit())->toBe('01:30')
        ->and(collect(config('backup.notifications.notifications'))->flatten()->all())->toBe([]);
});

test('an SFTP target becomes a disk, retention and archive password are applied', function () {
    BackupEinstellungen::speichern([
        'backup_ziel_art' => 'sftp', 'backup_ziel_host' => 'nas.firma.local', 'backup_ziel_benutzer' => 'backup',
        'backup_ziel_geheim_passwort' => 'geheim123', 'backup_ziel_pfad' => '/sicherung',
        'backup_tage_taeglich' => '14', 'backup_geheim_archivpasswort' => 'archiv-passwort', 'backup_mail' => 'it@firma.de',
    ]);
    BackupEinstellungen::anwenden();
    BackupEinstellungen::anwenden();

    expect(config('backup.backup.destination.disks'))->toBe(['local', BackupEinstellungen::ZIEL_DISK])
        ->and(config('filesystems.disks.'.BackupEinstellungen::ZIEL_DISK.'.driver'))->toBe('sftp')
        ->and(config('filesystems.disks.'.BackupEinstellungen::ZIEL_DISK.'.port'))->toBe(22)
        ->and(config('filesystems.disks.'.BackupEinstellungen::ZIEL_DISK.'.password'))->toBe('geheim123')
        ->and(config('backup.cleanup.default_strategy.keep_daily_backups_for_days'))->toBe(14)
        ->and(config('backup.backup.password'))->toBe('archiv-passwort')
        // Encrypted archive: the .env with APP_KEY goes in.
        ->and(config('backup.backup.source.files.exclude'))->not->toContain(base_path('.env'))
        // ...and is really encrypted: the package's listener is registered,
        // once, although the password came after the package booted.
        ->and(collect(Event::getRawListeners()[BackupZipWasCreated::class] ?? [])
            ->filter(fn ($l) => $l === EncryptBackupArchive::class)->count())->toBe(1)
        ->and(config('backup.notifications.mail.to'))->toBe('it@firma.de');

    // Secrets are stored encrypted.
    expect(Setting::wert('backup_ziel_geheim_passwort'))->not->toBe('geheim123');
});

test('an empty BACKUP_ARCHIVE_PASSWORD is no password', function () {
    config(['backup.backup.password' => '']);
    BackupEinstellungen::anwenden();

    expect(config('backup.backup.password'))->toBeNull()
        ->and(EncryptBackupArchive::shouldEncrypt())->toBeFalse();
});

test('without an archive password the .env stays out of the backup', function () {
    BackupEinstellungen::anwenden();

    expect(config('backup.backup.source.files.exclude'))->toContain(base_path('.env'));
});

test('FTP without keeping local writes only to the target', function () {
    BackupEinstellungen::speichern(['backup_ziel_art' => 'ftp', 'backup_ziel_host' => 'ftp.firma.de', 'backup_ziel_benutzer' => 'u', 'backup_lokal_behalten' => '0', 'backup_ziel_ftps' => '1']);
    BackupEinstellungen::anwenden();

    expect(config('backup.backup.destination.disks'))->toBe([BackupEinstellungen::ZIEL_DISK])
        ->and(config('filesystems.disks.'.BackupEinstellungen::ZIEL_DISK.'.ssl'))->toBeTrue();
});

test('the settings page saves, keeps secrets when left empty and needs the settings right', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminBackupEinstellungen::class)
        ->set('form.backup_uhrzeit', '03:15')
        // Port 1 on loopback: the folder check on save is refused at once
        // instead of waiting for a name lookup.
        ->set('form.backup_ziel_host', '127.0.0.1')
        ->set('form.backup_ziel_port', 1)
        ->set('form.backup_ziel_host', 'nas.firma.local')
        ->set('form.backup_ziel_benutzer', 'backup')
        ->set('form.backup_ziel_geheim_passwort', 'erstes-passwort')
        ->call('speichern')
        ->assertHasNoErrors()
        // Secrets never go back to the browser.
        ->assertSet('form.backup_ziel_geheim_passwort', '')
        ->set('form.backup_ziel_host', '127.0.0.2')
        ->call('speichern')
        ->assertHasNoErrors();

    $werte = BackupEinstellungen::werte();
    expect($werte['backup_uhrzeit'])->toBe('03:15')
        ->and($werte['backup_ziel_host'])->toBe('127.0.0.2')
        ->and($werte['backup_ziel_geheim_passwort'])->toBe('erstes-passwort');
});

test('a target without server or user is refused', function () {
    $this->actingAs(userWithPermissions(['admin_setting']));

    Livewire::test(AdminBackupEinstellungen::class)
        ->set('form.backup_ziel_art', 'sftp')
        ->call('speichern')
        ->assertHasErrors(['form.backup_ziel_host', 'form.backup_ziel_benutzer']);
});

test('without the settings right the backup page stays closed', function () {
    $this->actingAs(userWithPermissions(['admin_statistik']));

    $this->get(route('admin.backup.einstellungen'))->assertForbidden();
});

test('an existing backup can be downloaded, other paths cannot', function () {
    Storage::fake('local');
    $name = config('backup.backup.name');
    Storage::disk('local')->put("$name/2026-10-06-01-30-00.zip", 'zip');
    Storage::disk('local')->put('.env', 'geheim');
    config(['backup.backup.destination.disks' => ['local']]);
    $this->actingAs(userWithPermissions(['admin_setting']));

    $this->get(route('admin.backup.download', ['disk' => 'local', 'pfad' => "$name/2026-10-06-01-30-00.zip"]))
        ->assertOk()->assertDownload('2026-10-06-01-30-00.zip');
    $this->get(route('admin.backup.download', ['disk' => 'local', 'pfad' => '.env']))->assertNotFound();
    $this->get(route('admin.backup.download', ['disk' => 'public', 'pfad' => "$name/2026-10-06-01-30-00.zip"]))->assertNotFound();
});

test('without the settings right no backup can be downloaded', function () {
    $this->actingAs(userWithPermissions(['admin_statistik']));

    $this->get(route('admin.backup.download', ['disk' => 'local', 'pfad' => 'x.zip']))->assertForbidden();
});
