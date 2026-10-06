<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Support\BackupEinstellungen;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Spatie\Backup\BackupDestination\BackupDestination;

/**
 * Admin -> Einstellungen -> Backup: the backup of DokuVault itself -
 * schedule, external target (SFTP / FTP / FTPS), retention, archive
 * password, notifications, the existing backups and "back up now".
 */
class AdminBackupEinstellungen extends Component
{
    public array $form = [];

    /** Result of "Verbindung testen": [ok, message]. */
    public ?array $test = null;

    /**
     * Whether the list also asks the external target. Only on request: an
     * unreachable NAS would otherwise hold up every render of this page for
     * the length of the connect timeout.
     */
    public bool $externAbrufen = false;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->form = BackupEinstellungen::werte();
        // Secrets are never sent to the browser - an empty field keeps them.
        foreach (array_keys($this->form) as $schluessel) {
            if (str_contains($schluessel, '_geheim_')) {
                $this->form[$schluessel] = '';
            }
        }
    }

    protected function regeln(): array
    {
        return [
            'form.backup_aktiv' => ['boolean'],
            'form.backup_uhrzeit' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'form.backup_ziel_art' => ['required', 'in:'.implode(',', array_keys(BackupEinstellungen::ARTEN))],
            'form.backup_ziel_host' => ['required_unless:form.backup_ziel_art,keins', 'nullable', 'string', 'max:255'],
            'form.backup_ziel_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'form.backup_ziel_benutzer' => ['required_unless:form.backup_ziel_art,keins', 'nullable', 'string', 'max:255'],
            'form.backup_ziel_geheim_passwort' => ['nullable', 'string', 'max:255'],
            'form.backup_ziel_geheim_schluessel' => ['nullable', 'string', 'max:20000'],
            'form.backup_ziel_pfad' => ['nullable', 'string', 'max:255'],
            'form.backup_ziel_ftps' => ['boolean'],
            'form.backup_lokal_behalten' => ['boolean'],
            'form.backup_tage_alle' => ['required', 'integer', 'min:1', 'max:365'],
            'form.backup_tage_taeglich' => ['required', 'integer', 'min:0', 'max:365'],
            'form.backup_wochen' => ['required', 'integer', 'min:0', 'max:520'],
            'form.backup_monate' => ['required', 'integer', 'min:0', 'max:240'],
            'form.backup_max_mb' => ['required', 'integer', 'min:100', 'max:10000000'],
            'form.backup_geheim_archivpasswort' => ['nullable', 'string', 'min:8', 'max:255'],
            'form.backup_mail' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** The form values to store: empty secrets keep the stored ones. */
    protected function zuSpeichern(): array
    {
        $werte = $this->form;
        foreach ($werte as $schluessel => $wert) {
            if (str_contains($schluessel, '_geheim_') && blank($wert)) {
                unset($werte[$schluessel]);
            }
        }
        foreach (['backup_aktiv', 'backup_ziel_ftps', 'backup_lokal_behalten'] as $schalter) {
            $werte[$schalter] = ! empty($werte[$schalter]) ? '1' : '0';
        }

        return $werte;
    }

    public function speichern(): void
    {
        Gate::authorize('admin_setting');
        $this->validate($this->regeln(), [], ['form.backup_ziel_host' => __('Server'), 'form.backup_ziel_benutzer' => __('Benutzer')]);

        BackupEinstellungen::speichern($this->zuSpeichern());
        BackupEinstellungen::anwenden();

        // The folder now, not in the first night: a backup into a missing
        // folder fails.
        try {
            BackupEinstellungen::zielordnerAnlegen();
        } catch (\Throwable) {
            // Reported by "Verbindung testen" with the reason.
        }
        $this->mount();

        $this->dispatch('hinweis', text: __('Backup-Einstellungen gespeichert.'));
    }

    /** Clears a stored secret (password, key, archive password). */
    public function geheimnisLoeschen(string $schluessel): void
    {
        Gate::authorize('admin_setting');
        abort_unless(str_contains($schluessel, '_geheim_') && array_key_exists($schluessel, BackupEinstellungen::STANDARD), 404);

        Setting::setzen($schluessel, '');
        BackupEinstellungen::anwenden();
        $this->dispatch('hinweis', text: __('Gelöscht.'));
    }

    /**
     * Writes, reads and deletes a small file on the external target with the
     * values in the form - before saving, so a typo shows up here and not
     * in the first failed night.
     */
    public function verbindungTesten(): void
    {
        Gate::authorize('admin_setting');
        $this->validate($this->regeln());

        $werte = array_merge(BackupEinstellungen::werte(), $this->zuSpeichern());
        $disk = BackupEinstellungen::zielDisk($werte);
        if (! $disk) {
            $this->test = [false, __('Kein externes Ziel gewählt.')];

            return;
        }

        try {
            BackupEinstellungen::zielordnerAnlegen($werte);
            $ziel = Storage::build(['throw' => true] + $disk);
            $datei = 'dokuvault-verbindungstest-'.bin2hex(random_bytes(4)).'.txt';
            $ziel->put($datei, 'DokuVault '.now()->toIso8601String());
            $gelesen = $ziel->get($datei);
            $ziel->delete($datei);

            $this->test = $gelesen !== null
                ? [true, __('Verbindung in Ordnung: Datei geschrieben, gelesen und wieder gelöscht.')]
                : [false, __('Datei geschrieben, aber nicht wieder lesbar.')];
        } catch (\Throwable $e) {
            $this->test = [false, __('Verbindung fehlgeschlagen: :fehler', ['fehler' => $e->getMessage()])];
        }
    }

    /** Starts a backup in the background (queue, picked up within a minute). */
    public function jetztSichern(): void
    {
        Gate::authorize('admin_setting');

        dispatch(function () {
            Artisan::call('backup:run');
        });

        $this->dispatch('hinweis', text: __('Sicherung gestartet – sie erscheint in ein paar Minuten in der Liste.'));
    }

    /** Existing backups per target, newest first. */
    protected function sicherungen(): array
    {
        $name = config('backup.backup.name');
        $liste = [];

        foreach (config('backup.backup.destination.disks', []) as $disk) {
            if ($disk === BackupEinstellungen::ZIEL_DISK && ! $this->externAbrufen) {
                continue;
            }
            $ziel = $disk === BackupEinstellungen::ZIEL_DISK ? __('extern') : __('lokal');
            try {
                foreach (BackupDestination::create($disk, $name)->backups() as $sicherung) {
                    $liste[] = [
                        'ziel' => $ziel,
                        'disk' => $disk,
                        'pfad' => $sicherung->path(),
                        'datum' => $sicherung->date(),
                        'groesse' => $sicherung->sizeInBytes(),
                    ];
                }
            } catch (\Throwable $e) {
                $liste[] = ['ziel' => $ziel, 'fehler' => $e->getMessage()];
            }
        }

        return collect($liste)->sortByDesc(fn ($s) => $s['datum'] ?? null)->values()->all();
    }

    public function render()
    {
        return view('livewire.admin-backup-einstellungen', [
            'arten' => BackupEinstellungen::ARTEN,
            'gespeichert' => BackupEinstellungen::werte(),
            'sicherungen' => $this->sicherungen(),
            'hatExtern' => in_array(BackupEinstellungen::ZIEL_DISK, config('backup.backup.destination.disks', []), true),
        ])->layout('layouts.admin.app');
    }
}
