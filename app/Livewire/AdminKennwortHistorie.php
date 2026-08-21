<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vorherige Kennwoerter ueber alle Kunden - und die Frist, nach der sie gehen.
 *
 * Am Geraet steht der Verlauf dort, wo man ihn im Alltag braucht: unter dem
 * Kennwortfeld. Diese Seite ist fuer den anderen Fall - man weiss, dass etwas
 * falsch geaendert wurde, aber nicht mehr, wo.
 */
class AdminKennwortHistorie extends Component
{
    use WithPagination;

    /** Freitext ueber Objektname und Feld. */
    #[Url]
    public string $suche = '';

    /** Auf einen Kunden einschraenken. */
    #[Url]
    public string $kunde = '';

    /** Aufbewahrungsfrist in Tagen, direkt hier einstellbar. */
    public int $tage = 0;

    /** Welche Zeilen der Nutzer aufgedeckt hat - nach Eintrags-Id. */
    public array $aufgedeckt = [];

    public function mount(): void
    {
        Gate::authorize('see_hidden');

        $this->tage = Setting::passwortHistorieTage();
    }

    public function updated(string $eigenschaft): void
    {
        if (in_array($eigenschaft, ['suche', 'kunde'], true)) {
            $this->resetPage();
        }
    }

    public function fristSpeichern(): void
    {
        Gate::authorize('see_hidden');

        $this->validate(
            ['tage' => ['required', 'integer', 'min:0', 'max:3650']],
            [],
            ['tage' => __('Aufbewahrung')]
        );

        Setting::setzen(Setting::PASSWORT_HISTORIE_TAGE, $this->tage);

        $this->dispatch('hinweis', text: $this->tage === 0
            ? __('Es wird nichts mehr aufbewahrt. Der nächtliche Lauf räumt ab, was noch da ist.')
            : __('Kennwörter werden :tage Tage aufbewahrt.', ['tage' => $this->tage]));
    }

    /**
     * Ein Kennwort sichtbar machen.
     *
     * Erst auf Klick und einzeln: Eine Seite, die fuenfzig alte Kennwoerter im
     * Klartext ausliefert, weil jemand sie geoeffnet hat, waere schlechter als
     * das Protokoll, das wir gerade davon befreit haben.
     */
    public function aufdecken(int $id): void
    {
        Gate::authorize('see_hidden');

        $eintrag = PasswordHistory::findOrFail($id);

        $this->aufgedeckt[$id] = $eintrag->value;
    }

    public function verbergen(int $id): void
    {
        unset($this->aufgedeckt[$id]);
    }

    public function loeschen(int $id): void
    {
        Gate::authorize('see_hidden');

        PasswordHistory::findOrFail($id)->delete();
        unset($this->aufgedeckt[$id]);

        $this->dispatch('hinweis', text: __('Eintrag gelöscht.'));
    }

    public function render()
    {
        $abfrage = PasswordHistory::query()
            ->with(['user:id,name', 'customer:id,name'])
            ->when($this->kunde !== '', fn ($a) => $a->where('customer_id', (int) $this->kunde))
            ->when($this->suche !== '', fn ($a) => $a->where(
                fn ($teil) => $teil->where('subject_name', 'like', '%'.$this->suche.'%')
                    ->orWhere('field', 'like', '%'.$this->suche.'%')
                    ->orWhere('subject_type', 'like', '%'.$this->suche.'%')
            ))
            ->latest('id');

        return view('livewire.admin-kennwort-historie', [
            'eintraege' => $abfrage->paginate(25),
            'gesamt' => PasswordHistory::count(),
            'kunden' => Customer::orderBy('name')->pluck('name', 'id'),
            'feldNamen' => config('custom.secret_field_labels'),
        ])->layout('layouts.admin.app');
    }
}
