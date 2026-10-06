<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Measured values of the installation itself - database, files, disk,
 * queue, memory. For Admin -> Statistik -> System and the daily snapshot.
 * Every value that cannot be read (other database, no /proc) is null
 * instead of a wrong number.
 */
class SystemWerte
{
    /** Size of the database in bytes (MySQL/MariaDB: tables, SQLite: file). */
    public static function datenbankBytes(): ?int
    {
        try {
            return match (DB::connection()->getDriverName()) {
                'mysql', 'mariadb' => (int) DB::selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS b FROM information_schema.tables WHERE table_schema = DATABASE()'
                )->b,
                'sqlite' => is_file($pfad = DB::connection()->getDatabaseName()) ? (int) filesize($pfad) : null,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** The largest tables (MySQL/MariaDB only), name => bytes. */
    public static function groessteTabellen(int $anzahl = 8): array
    {
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                return [];
            }

            return collect(DB::select(
                'SELECT table_name AS n, (data_length + index_length) AS b FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY b DESC LIMIT ?',
                [$anzahl]
            ))->mapWithKeys(fn ($t) => [$t->n => (int) $t->b])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Bytes below a folder; 0 if it does not exist. */
    public static function verzeichnisBytes(string $pfad): int
    {
        if (! is_dir($pfad)) {
            return 0;
        }

        $summe = 0;
        $dateien = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pfad, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($dateien as $datei) {
            if ($datei->isFile()) {
                $summe += $datei->getSize();
            }
        }

        return $summe;
    }

    /** Backups of DokuVault itself (spatie/laravel-backup, local disk). */
    public static function backupBytes(): int
    {
        return self::verzeichnisBytes(storage_path('app/'.config('backup.backup.name', config('app.name'))));
    }

    /** Documents and other uploads below storage/app, without the backups. */
    public static function dateienBytes(): int
    {
        return max(0, self::verzeichnisBytes(storage_path('app')) - self::backupBytes());
    }

    public static function platteFreiBytes(): ?int
    {
        $wert = @disk_free_space(base_path());

        return $wert === false ? null : (int) $wert;
    }

    public static function platteGesamtBytes(): ?int
    {
        $wert = @disk_total_space(base_path());

        return $wert === false ? null : (int) $wert;
    }

    /** Waiting and failed background jobs. */
    public static function warteschlange(): array
    {
        return [
            'wartend' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'fehlgeschlagen' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
        ];
    }

    /** Memory of the machine from /proc/meminfo (Linux), in bytes. */
    public static function arbeitsspeicher(): ?array
    {
        if (! is_readable('/proc/meminfo')) {
            return null;
        }

        preg_match_all('/^(MemTotal|MemAvailable):\s+(\d+) kB/m', (string) @file_get_contents('/proc/meminfo'), $treffer, PREG_SET_ORDER);
        $werte = collect($treffer)->mapWithKeys(fn ($t) => [$t[1] => (int) $t[2] * 1024]);

        return $werte->has(['MemTotal', 'MemAvailable'])
            ? ['gesamt' => $werte['MemTotal'], 'frei' => $werte['MemAvailable']]
            : null;
    }

    /** Load average 1/5/15 minutes and the number of CPU cores. */
    public static function last(): ?array
    {
        $last = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
        if ($last === false) {
            return null;
        }

        $kerne = is_readable('/proc/cpuinfo') ? substr_count((string) @file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1 : null;

        return ['werte' => $last, 'kerne' => $kerne];
    }

    /** Human readable size: 1.2 GB. */
    public static function lesbar(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $einheiten = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $wert = (float) $bytes;
        while ($wert >= 1024 && $i < count($einheiten) - 1) {
            $wert /= 1024;
            $i++;
        }

        return number_format($wert, $i === 0 ? 0 : 1, ',', '.').' '.$einheiten[$i];
    }
}
