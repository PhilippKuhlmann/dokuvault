<?php

namespace App\Livewire;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\TracksChanges;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Der Papierkorb ueber alle Kunden - zum Nachsehen und zum endgueltigen
 * Loeschen.
 *
 * Der Papierkorb beim Kunden zeigt dessen eigene Eintraege und kann sie
 * zurueckholen. Hier geht es um das Gegenteil: sehen, was sich ueber die Jahre
 * angesammelt hat, und es loswerden. Geloescht wird von Hand und nur, was der
 * Filter gerade zeigt - ein Zeitplan, der im Hintergrund Daten endgueltig
 * entfernt, waere hier das falsche Werkzeug.
 */
class AdminPapierkorb extends Component
{
    use WithPagination;

    /** Nur Eintraege, die laenger als so viele Tage im Papierkorb liegen. 0 = alle. */
    #[Url]
    public int $aelterAls = 0;

    /** Auf eine Art einschraenken, etwa nur Server. */
    #[Url]
    public string $art = '';

    /** Auf einen Kunden einschraenken. */
    #[Url]
    public string $kunde = '';

    public bool $loeschenGefragt = false;

    /** Je Art werden hoechstens so viele Eintraege geholt. */
    protected const HOECHSTENS_JE_ART = 500;

    /** Wahr, sobald eine Art an die Obergrenze gestossen ist. */
    protected bool $gekuerzt = false;

    public function mount(): void
    {
        Gate::authorize('admin_trash');
    }

    /**
     * Nur bei den Filtern zurueck auf Seite eins und die Rueckfrage schliessen.
     *
     * Ohne den Namen zu pruefen feuert der Hook bei jeder Eigenschaft - auch
     * bei loeschenGefragt selbst, das sich damit sofort wieder zuruecksetzte.
     * Der Knopf tat dann schlicht nichts.
     */
    public function updated(string $eigenschaft): void
    {
        if (! in_array($eigenschaft, ['aelterAls', 'art', 'kunde'], true)) {
            return;
        }

        $this->resetPage();
        $this->loeschenGefragt = false;
    }

    /**
     * Alle passenden Eintraege, quer ueber die Arten.
     *
     * Bewusst im Speicher zusammengefuehrt statt per UNION: Es sind
     * unterschiedliche Tabellen mit unterschiedlichen Spalten, und die Zahlen
     * bleiben ueberschaubar - ein Papierkorb ist kein Datenlager.
     */
    protected function sammeln(): Collection
    {
        $grenze = $this->aelterAls > 0 ? now()->subDays($this->aelterAls) : null;
        $eintraege = collect();
        $this->gekuerzt = false;

        foreach (config('custom.trashables') as $slug => [$klasse, $bezeichnung]) {
            if ($this->art !== '' && $this->art !== $slug) {
                continue;
            }

            $abfrage = $klasse::onlyTrashed();

            if ($grenze) {
                $abfrage->where('deleted_at', '<', $grenze);
            }

            if ($this->kunde !== '') {
                $abfrage->where('customer_id', (int) $this->kunde);
            }

            $gefunden = $abfrage->orderByDesc('deleted_at')->limit(static::HOECHSTENS_JE_ART)->get();

            // Wer die Grenze nicht sieht, haelt die Zahl oben fuer den ganzen
            // Bestand und wundert sich, warum nach dem Loeschen noch etwas da
            // ist.
            if ($gefunden->count() === static::HOECHSTENS_JE_ART) {
                $this->gekuerzt = true;
            }

            foreach ($gefunden as $eintrag) {
                $eintraege->push([
                    'slug' => $slug,
                    'art' => $bezeichnung,
                    'id' => $eintrag->id,
                    'kunde' => $eintrag->customer_id,
                    'name' => $this->anzeigename($eintrag),
                    'geloescht' => $eintrag->deleted_at,
                ]);
            }
        }

        return $eintraege->sortByDesc('geloescht')->values();
    }

    protected function anzeigename($eintrag): string
    {
        foreach (['name', 'ssid', 'username', 'mailAdress', 'domain', 'provider', 'description', 'host', 'key'] as $feld) {
            if (! empty($eintrag->{$feld})) {
                return (string) $eintrag->{$feld};
            }
        }

        return '#'.$eintrag->id;
    }

    /**
     * Einen einzelnen Eintrag endgueltig entfernen.
     */
    public function loeschen(string $slug, int $id): void
    {
        Gate::authorize('admin_trash');

        // Die Klasse kommt aus der Whitelist, nie aus der Anfrage.
        $eintrag = config("custom.trashables.$slug");
        abort_unless($eintrag, 404);

        [$klasse] = $eintrag;
        $objekt = $klasse::onlyTrashed()->findOrFail($id);

        $this->endgueltig($objekt);

        $this->dispatch('hinweis', text: __('Eintrag endgültig gelöscht.'));
    }

    /**
     * Alles loeschen, was der Filter gerade zeigt.
     */
    public function alleLoeschen(): void
    {
        Gate::authorize('admin_trash');

        $anzahl = 0;

        foreach ($this->sammeln() as $zeile) {
            [$klasse] = config("custom.trashables.{$zeile['slug']}");
            $objekt = $klasse::onlyTrashed()->find($zeile['id']);

            if ($objekt) {
                $this->endgueltig($objekt);
                $anzahl++;
            }
        }

        $this->loeschenGefragt = false;
        $this->resetPage();

        $this->dispatch('hinweis', text: $anzahl === 1
            ? __('Ein Eintrag endgültig gelöscht.')
            : __(':anzahl Einträge endgültig gelöscht.', ['anzahl' => $anzahl]));
    }

    /**
     * Ein Objekt mitsamt allem, was an ihm haengt.
     *
     * Die Datei liegt ausserhalb der Datenbank und verschwindet nicht von
     * selbst; IP-Adressen und Zugangsdaten haengen polymorph daran und wuerden
     * auf eine Id zeigen, die es nicht mehr gibt.
     */
    protected function endgueltig($objekt): void
    {
        if (! empty($objekt->file_path)) {
            Storage::disk('local')->delete($objekt->file_path);
        }

        $traits = class_uses_recursive($objekt);

        if (in_array(HasIpAddresses::class, $traits, true)) {
            $objekt->ipAddresses()->delete();
        }

        if (in_array(HasCredentials::class, $traits, true)) {
            $objekt->credentialLinks()->delete();
        }

        // Sonst blieben die alten Kennwoerter eines endgueltig geloeschten
        // Geraets liegen - verschluesselt, aber ohne Objekt, zu dem sie
        // gehoeren, und ohne Frist, die sie je erreichen wuerde.
        if (in_array(TracksChanges::class, $traits, true)) {
            $objekt->kennwortVerlauf()->delete();
        }

        $objekt->forceDelete();
    }

    public function render()
    {
        $alle = $this->sammeln();
        $proSeite = 50;

        return view('livewire.admin-papierkorb', [
            'eintraege' => $alle->forPage($this->getPage(), $proSeite),
            'gesamt' => $alle->count(),
            'arten' => collect(config('custom.trashables'))->map(fn ($e) => $e[1]),
            'kunden' => Customer::orderBy('name')->pluck('name', 'id'),
            'seiten' => (int) ceil($alle->count() / $proSeite),
            'proSeite' => $proSeite,
            'gekuerzt' => $this->gekuerzt,
            'hoechstens' => static::HOECHSTENS_JE_ART,
        ])->layout('layouts.admin.app');
    }
}
