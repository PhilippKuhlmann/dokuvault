<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Support\Zeit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Name und Logos der Installation - ohne Speichern-Knopf.
 *
 * Jede Aenderung geht sofort in die Einstellungen: Wer ein Logo auswaehlt,
 * hat es damit gesetzt, und wer den Namen tippt, sieht ihn nach kurzer Pause
 * uebernommen. Ein Formular mit Speichern-Knopf laesst offen, ob die letzte
 * Aenderung noch drin war - hier gibt es diesen Zwischenzustand nicht.
 */
class AdminAllgemein extends Component
{
    use WithFileUploads;

    /** Eigener Name, leer heisst: der aus der Konfiguration. */
    public string $name = '';

    /**
     * Zeitzone der Anzeige.
     *
     * Nur der Anzeige: Gespeichert wird weiter in UTC. Waere es anders,
     * schriebe die Anwendung ab der Umstellung lokale Zeiten in dieselben
     * Spalten, in denen bereits UTC steht - zwei Zeitzonen in einer Spalte
     * ohne Merkmal, welche Zeile welche ist. Siehe App\Support\Zeit.
     */
    public string $zeitzone = '';

    /**
     * Groesste erlaubte Datei in Megabyte.
     *
     * In MB, nicht in Kilobyte: Wer eine Grenze setzt, denkt in Megabyte.
     * Gespeichert wird weiterhin in KB, weil die Validierungsregeln so
     * rechnen.
     */
    public int $uploadMb = 0;

    /** Sprache der Installation - die letzte Stufe, wenn Nutzer und Browser nichts sagen. */
    public string $sprache = '';

    /**
     * Ein Satz auf der Anmeldeseite, etwa wer bei Fragen hilft.
     *
     * Escaped ausgegeben, kein {!! !!}: Sonst waere das Feld ein Weg, HTML in
     * die Anmeldeseite zu schreiben - die eine Seite, die jeder sieht, auch
     * ohne Zugang.
     */
    public string $anmeldeHinweis = '';

    /** Zeilen je Seite, in den Kundenlisten und im Adminbereich. */
    public int $seiteListe = 0;

    public int $seiteAdmin = 0;

    /** Je Stelle ein Upload-Feld; die Namen muessen die Stellen spiegeln. */
    public $logo_login;

    public $logo_header;

    public $logo_favicon;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->name = (string) Setting::wert(Setting::APP_NAME);
        $this->zeitzone = Zeit::zone();
        $this->uploadMb = (int) round(Setting::uploadMaxKb() / 1024);
        $this->sprache = Setting::sprache();
        $this->anmeldeHinweis = Setting::anmeldeHinweis();
        $this->seiteListe = Setting::seiteListe();
        $this->seiteAdmin = Setting::seiteAdmin();
    }

    /**
     * Der Name wird beim Tippen gespeichert.
     *
     * Leer heisst "wieder den aus der Konfiguration nehmen", nicht "kein
     * Name" - deshalb null statt Leerstring.
     */
    public function updatedName(): void
    {
        Gate::authorize('admin_setting');

        $this->validate(['name' => ['nullable', 'string', 'max:60']], [], ['name' => __('Name')]);

        Setting::setzen(Setting::APP_NAME, trim($this->name) ?: null);

        $this->dispatch('hinweis', text: __('Name gespeichert.'));
    }

    /**
     * Die Obergrenze fuer Uploads.
     *
     * Nach oben durch den Server begrenzt: Ein hoeherer Wert waere ein
     * Versprechen, das nicht haelt - der Upload braeche mitten im Hochladen
     * ab, ohne verstaendliche Meldung.
     */
    public function updatedUploadMb(): void
    {
        Gate::authorize('admin_setting');

        $hoechstens = (int) floor(Setting::serverGrenzeKb() / 1024);

        $this->validate(
            ['uploadMb' => ['required', 'integer', 'min:1', 'max:'.$hoechstens]],
            ['uploadMb.max' => __('Mehr als :max MB nimmt dieser Server nicht an.', ['max' => $hoechstens])],
            ['uploadMb' => __('Größte Datei')]
        );

        Setting::setzen(Setting::UPLOAD_MAX_KB, $this->uploadMb * 1024);

        $this->dispatch('hinweis', text: __('Obergrenze gespeichert.'));
    }

    /** Die Zeitzone gilt sofort - wie der Name. */
    public function updatedZeitzone(): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            ['zeitzone' => ['required', Rule::in(Zeit::auswahl())]],
            [],
            ['zeitzone' => __('Zeitzone')]
        );

        Setting::setzen(Setting::APP_TIMEZONE, $this->zeitzone);

        $this->dispatch('hinweis', text: __('Zeitzone gespeichert.'));
    }

    /** Die Sprache der Installation - wirkt, wo Nutzer und Browser nichts sagen. */
    public function updatedSprache(): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            ['sprache' => ['required', Rule::in(array_keys(config('custom.locales', [])))]],
            [],
            ['sprache' => __('Sprache')]
        );

        Setting::setzen(Setting::APP_LOCALE, $this->sprache);

        $this->dispatch('hinweis', text: __('Sprache gespeichert.'));
    }

    /**
     * Der Hinweis auf der Anmeldeseite.
     *
     * 200 Zeichen: Es ist ein Satz, kein Aushang. Ausgegeben wird er escaped -
     * die Anmeldeseite ist die eine Seite, die jeder erreicht, auch ohne
     * Zugang.
     */
    public function updatedAnmeldeHinweis(): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            ['anmeldeHinweis' => ['nullable', 'string', 'max:200']],
            [],
            ['anmeldeHinweis' => __('Hinweis auf der Anmeldeseite')]
        );

        Setting::setzen(Setting::ANMELDE_HINWEIS, trim($this->anmeldeHinweis) ?: null);

        $this->dispatch('hinweis', text: __('Hinweis gespeichert.'));
    }

    public function updatedSeiteListe(): void
    {
        $this->seitengroesse('seiteListe', Setting::SEITE_LISTE, __('Zeilen je Seite in den Listen'));
    }

    public function updatedSeiteAdmin(): void
    {
        $this->seitengroesse('seiteAdmin', Setting::SEITE_ADMIN, __('Zeilen je Seite im Adminbereich'));
    }

    /**
     * Zeilen je Seite.
     *
     * Hoechstens 200: Eine Liste, die alles auf einmal zeigt, laedt bei einem
     * grossen Kunden jede Zeile samt Beziehungen - die Seite waere dann
     * langsam, ohne dass jemand den Zusammenhang zur Einstellung sieht.
     */
    private function seitengroesse(string $feld, string $schluessel, string $bezeichnung): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            [$feld => ['required', 'integer', 'min:5', 'max:200']],
            [],
            [$feld => $bezeichnung]
        );

        Setting::setzen($schluessel, $this->$feld);

        $this->dispatch('hinweis', text: __('Seitengröße gespeichert.'));
    }

    /**
     * Ein ausgewaehltes Logo sofort ablegen.
     *
     * Livewire ruft updated() nach jeder Aenderung auf - der Dateiname sagt,
     * welche Stelle gemeint war ("logo_header" -> "header").
     */
    public function updated(string $eigenschaft): void
    {
        if (! str_starts_with($eigenschaft, 'logo_')) {
            return;
        }

        Gate::authorize('admin_setting');

        $stelle = substr($eigenschaft, strlen('logo_'));
        abort_unless(in_array($stelle, Setting::LOGO_STELLEN, true), 404);

        $this->validate([
            $eigenschaft => ['required', 'image', 'mimes:'.implode(',', config('custom.bild_formate')), 'max:512'],
        ], [], [$eigenschaft => __('Logo')]);

        // Erst das alte weg, sonst bleibt bei jedem Wechsel eine Datei liegen,
        // die niemand mehr findet.
        $this->altesLoeschen($stelle);

        Setting::setzen(Setting::logoSchluessel($stelle), $this->{$eigenschaft}->store('branding', 'local'));

        // Das Feld leeren: Sonst haengt die Vorschau am hochgeladenen
        // Zwischenspeicher statt an der abgelegten Datei.
        $this->{$eigenschaft} = null;

        $this->dispatch('hinweis', text: __('Logo gespeichert.'));
        $this->seiteNeu();
    }

    /** Ein Logo entfernen - ein Knopf, keine Ankreuzbox mit Speichern danach. */
    public function entfernen(string $stelle): void
    {
        Gate::authorize('admin_setting');
        abort_unless(in_array($stelle, Setting::LOGO_STELLEN, true), 404);

        $this->altesLoeschen($stelle);
        Setting::setzen(Setting::logoSchluessel($stelle), null);

        $this->dispatch('hinweis', text: __('Logo entfernt.'));
        $this->seiteNeu();
    }

    /**
     * Nach einem Logo-Wechsel die Seite neu laden.
     *
     * Kopfzeile und Favicon stehen im Layout, nicht in dieser Komponente -
     * ohne Neuladen zeigte die Vorschau schon das neue Logo, die Kopfzeile
     * daneben aber noch das alte. Beim Tippen des Namens waere ein Neuladen
     * dagegen stoerend, dort bleibt es aus.
     */
    private function seiteNeu(): void
    {
        $this->redirect(route('admin.general.index'));
    }

    private function altesLoeschen(string $stelle): void
    {
        $alt = Setting::logoPfad($stelle);

        if ($alt !== null && Storage::disk('local')->exists($alt)) {
            Storage::disk('local')->delete($alt);
        }
    }

    public function render()
    {
        return view('livewire.admin-allgemein', [
            'standardName' => config('app.name'),
            'stellen' => collect(config('custom.branding_logos'))
                ->map(fn ($texte, $stelle) => [
                    'stelle' => $stelle,
                    'label' => $texte[0],
                    'hinweis' => $texte[1],
                    'vorhanden' => Setting::logoPfad($stelle) !== null,
                ])->values()->all(),
            'formate' => config('custom.bild_formate'),
            'zonen' => Zeit::auswahl(),
            // Damit die Seite zeigen kann, was die Umstellung bewirkt.
            'jetzt' => Zeit::anzeigen(now(), 'd.m.Y H:i'),
            // Damit auf der Seite steht, was der Server ueberhaupt hergibt.
            'serverMb' => (int) floor(Setting::serverGrenzeKb() / 1024),
            'phpWerte' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ])->layout('layouts.admin.app');
    }
}
