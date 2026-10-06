<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Events\BackupZipWasCreated;
use Spatie\Backup\Listeners\EncryptBackupArchive;

/**
 * The backup of DokuVault itself (spatie/laravel-backup), configured under
 * Admin -> Einstellungen -> Backup instead of in config/backup.php and
 * .env: schedule, an external target (SFTP or FTP/FTPS), retention,
 * archive password, notification address.
 *
 * Stored in settings; applied to the runtime config on every boot
 * (anwenden()), so backup:run, backup:clean and the admin page all see the
 * same. Secrets (passwords, SSH key) are stored encrypted.
 */
class BackupEinstellungen
{
    /** Disk name of the external target in config('filesystems.disks'). */
    public const ZIEL_DISK = 'dokuvault_extern';

    public const ARTEN = ['keins' => 'Nur lokal', 'sftp' => 'SFTP (SSH)', 'ftp' => 'FTP / FTPS'];

    /** Setting key => default. Keys with "_geheim_" are stored encrypted. */
    public const STANDARD = [
        'backup_aktiv' => '1',
        'backup_uhrzeit' => '01:30',
        'backup_ziel_art' => 'keins',
        'backup_ziel_host' => '',
        'backup_ziel_port' => '',
        'backup_ziel_benutzer' => '',
        'backup_ziel_geheim_passwort' => '',
        'backup_ziel_geheim_schluessel' => '',
        'backup_ziel_pfad' => '/dokuvault-backup',
        'backup_ziel_ftps' => '1',
        'backup_lokal_behalten' => '1',
        'backup_tage_alle' => '3',
        'backup_tage_taeglich' => '7',
        'backup_wochen' => '4',
        'backup_monate' => '6',
        'backup_max_mb' => '5000',
        'backup_geheim_archivpasswort' => '',
        'backup_mail' => '',
    ];

    /** All values, secrets decrypted. */
    public static function werte(): array
    {
        $werte = [];
        foreach (self::STANDARD as $schluessel => $standard) {
            $wert = Setting::wert($schluessel, $standard);
            if (str_contains($schluessel, '_geheim_') && filled($wert)) {
                try {
                    $wert = Crypt::decryptString($wert);
                } catch (\Throwable) {
                    $wert = '';
                }
            }
            $werte[$schluessel] = $wert ?? $standard;
        }

        return $werte;
    }

    public static function speichern(array $werte): void
    {
        foreach (self::STANDARD as $schluessel => $standard) {
            if (! array_key_exists($schluessel, $werte)) {
                continue;
            }
            $wert = (string) ($werte[$schluessel] ?? '');
            if (str_contains($schluessel, '_geheim_') && $wert !== '') {
                $wert = Crypt::encryptString($wert);
            }
            Setting::setzen($schluessel, $wert);
        }
    }

    public static function aktiv(): bool
    {
        return (bool) self::werte()['backup_aktiv'];
    }

    /** "HH:MM", checked - a broken value must not stop the scheduler. */
    public static function uhrzeit(): string
    {
        $zeit = (string) self::werte()['backup_uhrzeit'];

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $zeit) ? $zeit : '01:30';
    }

    /** Disk config of the external target, or null for "only local". */
    public static function zielDisk(?array $werte = null): ?array
    {
        $w = $werte ?? self::werte();

        return match ($w['backup_ziel_art']) {
            'sftp' => array_filter([
                'driver' => 'sftp',
                'host' => $w['backup_ziel_host'],
                'port' => (int) ($w['backup_ziel_port'] ?: 22),
                'username' => $w['backup_ziel_benutzer'],
                'password' => $w['backup_ziel_geheim_passwort'] ?: null,
                'privateKey' => $w['backup_ziel_geheim_schluessel'] ?: null,
                'root' => $w['backup_ziel_pfad'] ?: '/',
                'timeout' => 15,
            ], fn ($v) => $v !== null && $v !== ''),
            'ftp' => [
                'driver' => 'ftp',
                'host' => $w['backup_ziel_host'],
                'port' => (int) ($w['backup_ziel_port'] ?: 21),
                'username' => $w['backup_ziel_benutzer'],
                'password' => $w['backup_ziel_geheim_passwort'],
                'root' => $w['backup_ziel_pfad'] ?: '/',
                'ssl' => (bool) $w['backup_ziel_ftps'],
                'passive' => true,
                'timeout' => 15,
            ],
            default => null,
        };
    }

    /**
     * Creates the target folder if it is missing. SFTP and FTP create
     * subfolders on their own, but not the configured root itself - the
     * first write then failed with "not able to write the file". Done via a
     * disk rooted at "/", where the folder is just a path.
     */
    public static function zielordnerAnlegen(?array $werte = null): void
    {
        $disk = self::zielDisk($werte);
        $ordner = trim((string) ($disk['root'] ?? ''), '/');
        if (! $disk || $ordner === '') {
            return;
        }

        $oben = Storage::build(['root' => '/', 'throw' => true] + $disk);
        if (! $oben->directoryExists($ordner)) {
            $oben->makeDirectory($ordner);
        }
    }

    /** Disks the backup is written to. */
    public static function disks(?array $werte = null): array
    {
        $w = $werte ?? self::werte();
        $extern = self::zielDisk($w) !== null;

        // Without an external target local is the only place - it cannot
        // be switched off then.
        return array_values(array_filter([
            ! $extern || $w['backup_lokal_behalten'] ? 'local' : null,
            $extern ? self::ZIEL_DISK : null,
        ]));
    }

    /**
     * Writes the settings into the runtime config. Called on boot; without
     * the settings table (fresh install before migrate) nothing happens.
     */
    public static function anwenden(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $w = self::werte();

        if ($disk = self::zielDisk($w)) {
            config(['filesystems.disks.'.self::ZIEL_DISK => $disk]);
        }

        $disks = self::disks($w);
        config([
            'backup.backup.destination.disks' => $disks,
            'backup.monitor_backups.0.disks' => $disks,
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => (int) $w['backup_tage_alle'],
            'backup.cleanup.default_strategy.keep_daily_backups_for_days' => (int) $w['backup_tage_taeglich'],
            'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => (int) $w['backup_wochen'],
            'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => (int) $w['backup_monate'],
            'backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than' => (int) $w['backup_max_mb'],
        ]);

        // The .env holds APP_KEY - without it every stored password in the
        // dump is unreadable after a restore. It only goes into the archive
        // when the archive is encrypted; otherwise the page warns to keep
        // the key elsewhere.
        if (filled($w['backup_geheim_archivpasswort'])) {
            config([
                'backup.backup.password' => $w['backup_geheim_archivpasswort'],
                'backup.backup.encryption' => 'default',
                'backup.backup.source.files.exclude' => array_values(array_diff(
                    config('backup.backup.source.files.exclude', []),
                    [base_path('.env')],
                )),
            ]);

            // The package registers its encryption listener only if a
            // password is in the config when it boots - which is before
            // this runs. Without this the ZIP (and the .env in it) stayed
            // unencrypted.
            $angemeldet = Event::getRawListeners()[BackupZipWasCreated::class] ?? [];
            if (! in_array(EncryptBackupArchive::class, $angemeldet, true)) {
                Event::listen(BackupZipWasCreated::class, EncryptBackupArchive::class);
            }
        }

        // Without an address no mail at all - "admin@example.com" from the
        // package default went nowhere.
        if (filled($w['backup_mail'])) {
            config(['backup.notifications.mail.to' => $w['backup_mail']]);
        } else {
            config(['backup.notifications.notifications' => array_map(fn () => [], config('backup.notifications.notifications', []))]);
        }

        // The package keeps its config as an instance (scoped). If it was
        // built before these settings, backup:run would still write to the
        // old disks - forget it, it is rebuilt from the config above.
        app()->forgetInstance(Config::class);
    }
}
