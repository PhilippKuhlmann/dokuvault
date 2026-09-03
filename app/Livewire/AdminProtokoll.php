<?php

namespace App\Livewire;

use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Das Aktivitätsprotokoll mit Suche und Filtern.
 *
 * Vorher eine feste Liste, 50 Einträge je Seite, absteigend nach Zeit. Bei 863
 * Einträgen war die Frage "wer hat gestern an der Firewall etwas geändert?"
 * damit eine Blätterübung.
 *
 * Der Zugang haengt am Recht admin_activity, gesetzt an der Route. Das
 * Aufdecken eines bisherigen Kennworts prueft dasselbe Recht noch einmal - der
 * Aufruf kommt aus dem Browser und darf sich nicht auf die Route verlassen.
 */
class AdminProtokoll extends Component
{
    use WithPagination;

    /** Freitext über die Eigenschaften eines Eintrags. */
    #[Url(except: '')]
    public string $suche = '';

    /** Auf ein Ereignis einschränken, etwa nur Kennwortänderungen. */
    #[Url(except: '')]
    public string $ereignis = '';

    /** Auf eine Objektart einschränken, etwa nur Server. */
    #[Url(except: '')]
    public string $art = '';

    /** Auf einen Verursacher einschränken. */
    #[Url(except: '')]
    public string $benutzer = '';

    /** Nur die letzten X Tage. 0 heißt: alles. */
    #[Url(except: 0)]
    public int $tage = 0;

    public function updated(string $eigenschaft): void
    {
        if (in_array($eigenschaft, ['suche', 'ereignis', 'art', 'benutzer', 'tage'], true)) {
            $this->resetPage();
        }
    }

    public function zuruecksetzen(): void
    {
        $this->reset(['suche', 'ereignis', 'art', 'benutzer', 'tage']);
        $this->resetPage();
    }

    public function render()
    {
        $abfrage = Activity::with('causer.customer')
            ->when($this->ereignis !== '', fn ($a) => $a->where('event', $this->ereignis))
            ->when($this->art !== '', fn ($a) => $a->where('subject_type', $this->art))
            ->when($this->benutzer !== '', fn ($a) => $a->where('causer_id', (int) $this->benutzer))
            ->when($this->tage > 0, fn ($a) => $a->where('created_at', '>=', $this->grenze()))
            // Volltext über die Eigenschaften: In einem Protokoll sucht man
            // nicht nach einem Feld, sondern nach dem, woran man sich erinnert -
            // einem Namen, einer IP, einer Seriennummer.
            //
            // whereEnthaelt statt like: Ein Suchbegriff wie "SRV_01" fand sonst
            // auch "SRV101", und die Suche nach "%" lieferte alle 863 Einträge.
            ->when($this->suche !== '', fn ($a) => $a->where(
                fn ($teil) => $teil->whereEnthaelt(['properties', 'subject_type'], $this->suche)
                    ->orWhereHas('causer', fn ($c) => $c->whereEnthaelt('name', $this->suche))
            ))
            ->latest('id');

        return view('livewire.admin-protokoll', [
            // Bewusst nicht einstellbar wie die uebrigen Listen: Im Protokoll
            // sucht man nach einem Vorgang und ueberfliegt, statt zu lesen.
            'activities' => $abfrage->paginate(50),
            'gesamt' => Activity::count(),
            'ereignisse' => config('custom.activity_events'),
            'arten' => $this->arten(),
            // Nicht 'benutzer': So heisst schon die Filter-Eigenschaft, und die
            // gewinnt in der View - die Auswahlliste waere dort ein String.
            'benutzerListe' => $this->verursacher(),
            'gefiltert' => $this->suche !== '' || $this->ereignis !== '' || $this->art !== ''
                || $this->benutzer !== '' || $this->tage > 0,
        ])->layout('layouts.admin.app');
    }

    /**
     * Ab wann Einträge gezeigt werden.
     *
     * "heute" heißt heute, nicht "die letzten 24 Stunden": Um 18:46 zeigte der
     * Knopf sonst auch Einträge von gestern 18:20. Ab einer Woche ist der
     * Unterschied belanglos, dort bleibt es rollierend.
     */
    protected function grenze(): Carbon
    {
        return $this->tage === 1
            ? now()->startOfDay()
            : now()->subDays($this->tage);
    }

    /**
     * Die Objektarten, zu denen es überhaupt Einträge gibt.
     *
     * Aus der Tabelle und nicht aus einer Liste: Protokolliert wird auch, was
     * nicht im Papierkorb steht - IP-Adressen, Rollen, Zugangsdaten-Verknüpfungen.
     */
    protected function arten(): array
    {
        return Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->mapWithKeys(fn ($typ) => [$typ => class_basename($typ)])
            ->all();
    }

    /**
     * Wer im Protokoll vorkommt und noch existiert.
     *
     * Die Tabelle kennt 114 verschiedene Verursacher-Ids, die meisten davon aus
     * Beispieldaten längst gelöschter Konten. Eine Auswahl mit 114 Zeilen,
     * von denen 110 leer sind, hilft niemandem.
     */
    protected function verursacher(): array
    {
        $ids = Activity::whereNotNull('causer_id')->distinct()->pluck('causer_id');

        // Nach Herkunft getrennt: Ein Kundenzugang mit Schreibrecht aendert
        // Daten wie jeder Techniker, und genau dann will man nachsehen, was er
        // getan hat. In einer Liste aus lauter Namen liesse sich aber nicht
        // erkennen, wer zu wem gehoert - bei mehreren Kunden heissen die
        // Zugaenge schnell aehnlich.
        return User::whereIn('id', $ids)
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id'])
            ->groupBy(fn ($nutzer) => $nutzer->customer?->name ?? __('Mitarbeiter'))
            ->sortKeys()
            ->map(fn ($gruppe) => $gruppe->pluck('name', 'id'))
            ->all();
    }
}
