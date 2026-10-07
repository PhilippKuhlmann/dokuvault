<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * Whether DokuVault's own backup actually runs: the outcome of every
 * backup:run is noted per target (local / external), and from that the
 * dashboard and the system page show one line - green, amber or red.
 *
 * A mail on failure needs a working mail server; this needs nothing and is
 * seen by whoever opens the admin area.
 */
class BackupZustand
{
    public const SCHLUESSEL = 'backup_status';

    /** Older than this and the nightly backup was missed. */
    public const HOECHSTALTER_STUNDEN = 26;

    public static function erfolg(BackupWasSuccessful $ereignis): void
    {
        self::merken($ereignis->backupDestination->diskName(), null);
    }

    public static function fehler(BackupHasFailed $ereignis): void
    {
        self::merken($ereignis->backupDestination?->diskName(), $ereignis->exception->getMessage());
    }

    /**
     * Notes one outcome. $disk null means the run failed before any target
     * (database dump, zip). The message is cut - an SFTP error can carry a
     * whole stack of text.
     */
    public static function merken(?string $disk, ?string $fehler): void
    {
        $status = self::gelesen();
        $jetzt = now()->toIso8601String();
        $schluessel = $disk ?? '_lauf';

        if ($fehler === null) {
            $status[$schluessel]['ok'] = $jetzt;
        } else {
            $status[$schluessel]['fehler'] = $jetzt;
            $status[$schluessel]['meldung'] = mb_strimwidth($fehler, 0, 500, '…');
        }

        Setting::setzen(self::SCHLUESSEL, json_encode($status));
    }

    protected static function gelesen(): array
    {
        $status = json_decode((string) Setting::wert(self::SCHLUESSEL, ''), true);

        return is_array($status) ? $status : [];
    }

    /**
     * The state in one line:
     * ['stufe' => ok|warnung|fehler, 'text' => ..., 'letzte' => ?Carbon].
     */
    public static function zustand(): array
    {
        $status = self::gelesen();
        $werte = BackupEinstellungen::werte();
        $disks = BackupEinstellungen::disks($werte);
        $zeit = fn (?string $wert) => $wert ? Carbon::parse($wert) : null;

        // The newest successful copy on any target; for backups from before
        // this was noted, the newest file in the local folder.
        $letzte = collect($disks)
            ->map(fn ($disk) => $zeit($status[$disk]['ok'] ?? null))
            ->filter()->max() ?? self::neuesteLokaleDatei();

        $ergebnis = fn (string $stufe, string $text) => ['stufe' => $stufe, 'text' => $text, 'letzte' => $letzte];

        if (! $werte['backup_aktiv']) {
            return $ergebnis('warnung', __('Die automatische Sicherung ist ausgeschaltet.'));
        }

        // A failure newer than the last success - the whole run, or one target.
        $lauf = $status['_lauf'] ?? [];
        if (($f = $zeit($lauf['fehler'] ?? null)) && (! $letzte || $f->gt($letzte))) {
            return $ergebnis('fehler', __('Letzte Sicherung fehlgeschlagen: :meldung', ['meldung' => $lauf['meldung'] ?? '']));
        }
        foreach ($disks as $disk) {
            $eintrag = $status[$disk] ?? [];
            $f = $zeit($eintrag['fehler'] ?? null);
            $ok = $zeit($eintrag['ok'] ?? null);
            if ($f && (! $ok || $f->gt($ok))) {
                $ziel = $disk === BackupEinstellungen::ZIEL_DISK ? __('Externes Ziel') : __('Lokale Ablage');

                return $ergebnis('fehler', $ziel.': '.($eintrag['meldung'] ?? ''));
            }
        }

        if (! $letzte) {
            return $ergebnis('fehler', __('Es gibt noch keine Sicherung.'));
        }
        if ($letzte->lt(now()->subHours(self::HOECHSTALTER_STUNDEN))) {
            return $ergebnis('fehler', __('Die letzte Sicherung ist :alter alt.', ['alter' => $letzte->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE)]));
        }

        // Runs, but a restore would come back without APP_KEY or only from
        // this server.
        if (blank($werte['backup_geheim_archivpasswort'])) {
            return $ergebnis('warnung', __('Läuft, aber ohne Archivpasswort – der APP_KEY fehlt in der Sicherung.'));
        }
        if (BackupEinstellungen::zielDisk($werte) === null) {
            return $ergebnis('warnung', __('Läuft, liegt aber nur auf diesem Server.'));
        }

        return $ergebnis('ok', count($disks) > 1 ? __('Läuft – lokal und extern.') : __('Läuft – extern.'));
    }

    protected static function neuesteLokaleDatei(): ?Carbon
    {
        try {
            $disk = Storage::disk('local');
            $neueste = collect($disk->files(config('backup.backup.name')))
                ->filter(fn ($datei) => str_ends_with($datei, '.zip'))
                ->map(fn ($datei) => $disk->lastModified($datei))
                ->max();

            return $neueste ? Carbon::createFromTimestamp($neueste) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
