<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Name and logos of the installation - without a save button.
 *
 * Every change goes into the settings at once: whoever picks a logo has set
 * it, and whoever types the name sees it taken over after a short pause. A
 * form with a save button leaves it open whether the last change made it in
 * - here that in-between state does not exist.
 */
class AdminGeneral extends Component
{
    use WithFileUploads;

    /** Own name; empty means the one from the configuration. */
    public string $name = '';

    /**
     * Timezone of the display.
     *
     * Of the display only: storage stays UTC. Were it otherwise, the
     * application would write local times into the very columns that already
     * hold UTC from the switch onwards - two timezones in one column,
     * with nothing to tell which row is which. See App\Support\LocalTime.
     */
    public string $zeitzone = '';

    /**
     * Groesste erlaubte Datei in Megabyte.
     *
     * In MB, not in kilobytes: whoever sets a limit thinks in megabytes.
     * Storage stays in KB, because that is how the validation rules
     * compute.
     */
    public int $uploadMb = 0;

    /** Language of the installation - the last stage when user and browser say nothing. */
    public string $sprache = '';

    /**
     * A sentence on the sign-in page, for instance who helps with questions.
     *
     * Output escaped, no {!! !!}: otherwise the field would be a way to write
     * HTML into the sign-in page - the one page everybody sees, even without
     * an account.
     */
    public string $anmeldeHinweis = '';

    /**
     * Allowed file extensions - a selection from the list in the configuration.
     *
     * A selection, not free text: an allowlist anyone can enter "php" into is
     * none.
     *
     * Not "formate": render() hands out a view variable of that name holding
     * the image formats for the logo note, and a public property would have
     * shadowed it. The note under the logos then listed document formats -
     * seen in the browser, no test noticed it.
     */
    public array $endungen = [];

    /** Rows per page, in the customer lists and in the admin area. */
    public int $seiteListe = 0;

    public int $seiteAdmin = 0;

    /** One upload field per place; the names have to mirror the places. */
    public $logo_login;

    public $logo_header;

    public $logo_favicon;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->name = (string) Setting::wert(Setting::APP_NAME);
        $this->zeitzone = LocalTime::zone();
        $this->uploadMb = (int) round(Setting::uploadMaxKb() / 1024);
        $this->sprache = Setting::sprache();
        $this->anmeldeHinweis = Setting::anmeldeHinweis();
        $this->seiteListe = Setting::seiteListe();
        $this->seiteAdmin = Setting::seiteAdmin();
        $this->endungen = Setting::fileFormats();
    }

    /**
     * The name is saved while typing.
     *
     * Empty means "take the one from the configuration again", not "no name" -
     * hence null instead of an empty string.
     */
    public function updatedName(): void
    {
        Gate::authorize('admin_setting');

        $this->validate(['name' => ['nullable', 'string', 'max:60']], [], ['name' => __('Name')]);

        Setting::setzen(Setting::APP_NAME, trim($this->name) ?: null);

        $this->dispatch('notice', text: __('Name gespeichert.'));
    }

    /**
     * The upper limit for uploads.
     *
     * Capped by the server: a higher value would be a promise that does not
     * hold - the upload would break mid-transfer, without a message anyone
     * can understand.
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

        $this->dispatch('notice', text: __('Obergrenze gespeichert.'));
    }

    /** The timezone applies at once - like the name. */
    public function updatedZeitzone(): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            ['zeitzone' => ['required', Rule::in(LocalTime::options())]],
            [],
            ['zeitzone' => __('Zeitzone')]
        );

        Setting::setzen(Setting::APP_TIMEZONE, $this->zeitzone);

        $this->dispatch('notice', text: __('Zeitzone gespeichert.'));
    }

    /** The language of the installation - takes effect where user and browser say nothing. */
    public function updatedSprache(): void
    {
        Gate::authorize('admin_setting');

        $this->validate(
            ['sprache' => ['required', Rule::in(array_keys(config('custom.locales', [])))]],
            [],
            ['sprache' => __('Sprache')]
        );

        Setting::setzen(Setting::APP_LOCALE, $this->sprache);

        $this->dispatch('notice', text: __('Sprache gespeichert.'));
    }

    /**
     * The notice on the sign-in page.
     *
     * 200 characters: it is a sentence, not a notice board. It is output
     * escaped - the sign-in page is the one page everybody reaches, even
     * without an account.
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

        $this->dispatch('notice', text: __('Hinweis gespeichert.'));
    }

    /**
     * The allowed file extensions.
     *
     * At least one: without a selection the whole list from the configuration
     * applies again, and an emptied field would have achieved the opposite of
     * what the checkbox says - everything allowed instead of nothing.
     */
    public function updatedEndungen(): void
    {
        Gate::authorize('admin_setting');

        $erlaubt = config('custom.datei_formate', []);

        $this->validate([
            'endungen' => ['required', 'array', 'min:1'],
            'endungen.*' => [Rule::in($erlaubt)],
        ], [], ['endungen' => __('Erlaubte Dateiendungen')]);

        // Stored in the order of the configuration, not in the order of
        // clicking - or the same selection would read differently per path.
        $auswahl = array_values(array_intersect($erlaubt, $this->endungen));

        Setting::setzen(Setting::DATEI_FORMATE, implode(',', $auswahl));

        $this->dispatch('notice', text: __('Dateiendungen gespeichert.'));
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
     * Rows per page.
     *
     * At most 200: a list that shows everything at once loads every row with
     * its relations at a large customer - the page would then be slow without
     * anyone seeing the connection to the setting.
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

        $this->dispatch('notice', text: __('Seitengröße gespeichert.'));
    }

    /**
     * Store a chosen logo at once.
     *
     * Livewire calls updated() after every change - the field name says which
     * place was meant ("logo_header" -> "header").
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

        // The old one first, or a file stays behind with every change that
        // nobody finds any more.
        $this->deleteOld($stelle);

        Setting::setzen(Setting::logoSchluessel($stelle), $this->{$eigenschaft}->store('branding', 'local'));

        // Empty the field: otherwise the preview hangs on the uploaded
        // temporary file instead of on the stored one.
        $this->{$eigenschaft} = null;

        $this->dispatch('notice', text: __('Logo gespeichert.'));
        $this->createPage();
    }

    /** Remove a logo - one button, not a checkbox with a save afterwards. */
    public function entfernen(string $stelle): void
    {
        Gate::authorize('admin_setting');
        abort_unless(in_array($stelle, Setting::LOGO_STELLEN, true), 404);

        $this->deleteOld($stelle);
        Setting::setzen(Setting::logoSchluessel($stelle), null);

        $this->dispatch('notice', text: __('Logo entfernt.'));
        $this->createPage();
    }

    /**
     * Reload the page after a logo change.
     *
     * Header and favicon sit in the layout, not in this component - without a
     * reload the preview already showed the new logo while the header beside
     * it still showed the old one. While typing the name a reload would be
     * disturbing, so there it stays off.
     */
    private function createPage(): void
    {
        $this->redirect(route('admin.general.index'));
    }

    private function deleteOld(string $stelle): void
    {
        $alt = Setting::logoPfad($stelle);

        if ($alt !== null && Storage::disk('local')->exists($alt)) {
            Storage::disk('local')->delete($alt);
        }
    }

    public function render()
    {
        return view('livewire.admin-general', [
            'standardName' => config('app.name'),
            'stellen' => collect(config('custom.branding_logos'))
                ->map(fn ($texte, $stelle) => [
                    'stelle' => $stelle,
                    'label' => $texte[0],
                    'hinweis' => $texte[1],
                    'vorhanden' => Setting::logoPfad($stelle) !== null,
                ])->values()->all(),
            'formate' => config('custom.bild_formate'),
            'zonen' => LocalTime::options(),
            // So the page can show what the change brings about.
            'jetzt' => LocalTime::display(now(), 'd.m.Y H:i'),
            // So the page states what the server actually offers.
            'serverMb' => (int) floor(Setting::serverGrenzeKb() / 1024),
            'phpWerte' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ])->layout('layouts.admin.app');
    }
}
