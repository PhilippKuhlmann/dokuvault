<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\File;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Die Dateiliste mit Suche, Filtern und Sortierung.
 *
 * Vorher eine feste Liste, aelteste zuerst. Bei einem Kunden mit hundert
 * Dateien war "wo ist der Wartungsvertrag von 2024?" damit eine
 * Blaetteruebung - und die zuletzt hochgeladene Datei stand ganz hinten.
 */
class DateiListe extends Component
{
    use WithPagination;

    #[Locked]
    public int $customerId;

    /** Freitext ueber Bezeichnung und Endung. */
    #[Url(except: '')]
    public string $suche = '';

    /** Auf eine Art einschraenken, etwa nur PDF. */
    #[Url(except: '')]
    public string $art = '';

    /** Nur die letzten X Tage. 0 heisst: alles. */
    #[Url(except: 0)]
    public int $tage = 0;

    /** Sortierung: neueste, aelteste, name, groesse. */
    #[Url(except: 'neueste')]
    public string $sortierung = 'neueste';

    public function mount(Customer $customer): void
    {
        Gate::authorize('viewAny', File::class);

        $this->customerId = $customer->id;
    }

    public function updated(string $eigenschaft): void
    {
        if (in_array($eigenschaft, ['suche', 'art', 'tage', 'sortierung'], true)) {
            $this->resetPage();
        }
    }

    public function zuruecksetzen(): void
    {
        $this->reset(['suche', 'art', 'tage', 'sortierung']);
        $this->resetPage();
    }

    /**
     * Eine Datei loeschen - samt der Datei auf der Platte.
     *
     * Die Id wird gegen den Kunden geprueft: Sonst liesse sich mit einer
     * fremden Id die Datei eines anderen Kunden loeschen.
     */
    public function loeschen(int $id): void
    {
        Gate::authorize('delete', File::class);

        $datei = File::where('customer_id', $this->customerId)->find($id);

        // abort statt findOrFail: Das liefert einen sauberen 404 statt einer
        // durchgereichten Ausnahme.
        abort_unless($datei !== null, 404);

        Storage::disk('local')->delete($datei->file_path);
        $datei->delete();

        $this->dispatch('hinweis', text: __('Datei gelöscht.'));
    }

    public function render()
    {
        $abfrage = File::where('customer_id', $this->customerId)
            ->when($this->suche !== '', fn ($a) => $a->whereEnthaelt(['name', 'extension'], $this->suche))
            ->when($this->tage > 0, fn ($a) => $a->where('created_at', '>=', $this->grenze()))
            ->when($this->art !== '', fn ($a) => $this->artEinschraenken($a));

        $abfrage = match ($this->sortierung) {
            'aelteste' => $abfrage->oldest(),
            'name' => $abfrage->orderBy('name'),
            // Ohne gespeicherte Groesse (Bestandsdatei) nach hinten statt nach
            // vorn: Eine leere Angabe ist keine kleine Datei.
            'groesse' => $abfrage->orderByRaw('size IS NULL')->orderByDesc('size'),
            default => $abfrage->latest(),
        };

        return view('livewire.datei-liste', [
            'customer' => Customer::findOrFail($this->customerId),
            'files' => $abfrage->paginate(25),
            'gesamt' => File::where('customer_id', $this->customerId)->count(),
            'arten' => collect(config('custom.file_arten'))->map(fn ($a) => $a[0])->all(),
            'gefiltert' => $this->suche !== '' || $this->art !== '' || $this->tage > 0,
        ]);
    }

    /**
     * Auf eine Art einschraenken.
     *
     * "datei" ist der Rest - alles, was in keiner Endungsliste steht. Das
     * laesst sich nicht als Liste formulieren, sondern nur als Gegenteil.
     */
    private function artEinschraenken($abfrage)
    {
        // Kleingeschrieben vergleichen: Die Endung wird gespeichert, wie sie
        // hochgeladen wurde - "Vertrag.PDF" ist eine PDF-Datei und soll im
        // Filter nicht fehlen. Auf SQLite (Tests) vergleicht IN sonst
        // buchstabengetreu.
        $spalte = DB::raw('LOWER(extension)');

        if ($this->art === 'datei') {
            return $abfrage->whereNotIn($spalte, File::bekannteEndungen());
        }

        return $abfrage->whereIn($spalte, File::endungenFuerArt($this->art));
    }

    /**
     * Ab wann Dateien gezeigt werden.
     *
     * "heute" heisst heute, nicht "die letzten 24 Stunden" - dieselbe Regel
     * wie im Protokoll.
     */
    private function grenze(): Carbon
    {
        return $this->tage === 1 ? now()->startOfDay() : now()->subDays($this->tage);
    }
}
