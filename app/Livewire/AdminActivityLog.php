<?php

namespace App\Livewire;

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * The activity log with search and filters.
 *
 * It used to be a fixed list, 50 entries per page, newest first. At 863
 * entries the question "who changed something on the firewall yesterday?" was
 * a paging exercise.
 *
 * Access hangs on the permission admin_activity, set on the route. Revealing
 * a previous password checks the same permission again - the call comes from
 * the browser and must not rely on the route.
 */
class AdminActivityLog extends Component
{
    use WithPagination;

    /** Free text across the properties of an entry. */
    #[Url(except: '')]
    public string $suche = '';

    /** Limit to one event, password changes only for instance. */
    #[Url(except: '')]
    public string $ereignis = '';

    /** Limit to one object kind, servers only for instance. */
    #[Url(except: '')]
    public string $art = '';

    /** Limit to one causer. */
    #[Url(except: '')]
    public string $benutzer = '';

    /** Only changes to objects of this customer (id). */
    #[Url(except: '')]
    public string $kunde = '';

    /** The last X days only. 0 means everything. */
    #[Url(except: 0)]
    public int $tage = 0;

    public function updated(string $eigenschaft): void
    {
        if (in_array($eigenschaft, ['suche', 'ereignis', 'art', 'benutzer', 'kunde', 'tage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['suche', 'ereignis', 'art', 'benutzer', 'kunde', 'tage']);
        $this->resetPage();
    }

    public function render()
    {
        $abfrage = Activity::with('causer.customer')
            ->when($this->ereignis !== '', fn ($a) => $a->where('event', $this->ereignis))
            ->when($this->art !== '', fn ($a) => $a->where('subject_type', $this->art))
            // Kind AND id: an agent token with id 5 is not the user with id 5.
            // Without the kind the filter showed both.
            ->when($this->benutzer !== '', function ($a) {
                [$typ, $id] = $this->verursacherWahl();
                $a->where('causer_type', $typ)->where('causer_id', $id);
            })
            ->when($this->kunde !== '', fn ($a) => $this->onlyCustomer($a, (int) $this->kunde))
            ->when($this->tage > 0, fn ($a) => $a->where('created_at', '>=', $this->grenze()))
            // Full text across the properties: in a log one does not search
            // for a field but for what one remembers - a name, an IP, a serial
            // number.
            //
            // whereEnthaelt instead of like: a term like "SRV_01" would
            // otherwise find "SRV101" too, and a search for "%" returned all 863 entries.
            ->when($this->suche !== '', fn ($a) => $a->where(
                fn ($teil) => $teil->whereEnthaelt(['properties', 'subject_type'], $this->suche)
                    ->orWhereHas('causer', fn ($c) => $c->whereEnthaelt('name', $this->suche))
            ))
            ->latest('id');

        // Deliberately not configurable like the other lists: in the log one
        // searches for an event and skims rather than reads.
        $activities = $abfrage->paginate(50);

        return view('livewire.admin-activity-log', [
            'activities' => $activities,
            'kundeVon' => $this->customersOfEntries($activities->getCollection()),
            'kunden' => Customer::orderBy('name')->pluck('name', 'id')->all(),
            'gesamt' => Activity::count(),
            'ereignisse' => config('custom.activity_events'),
            'arten' => $this->types(),
            // Not 'benutzer': that is already the name of the filter property,
            // and it wins in the view - the select list would be a string there.
            'benutzerListe' => $this->verursacher(),
            'gefiltert' => $this->suche !== '' || $this->ereignis !== '' || $this->art !== ''
                || $this->benutzer !== '' || $this->kunde !== '' || $this->tage > 0,
        ])->layout('layouts.admin.app');
    }

    /**
     * Changes to objects of one customer, and to the customer itself.
     * Through the customer column of the entry (set on writing, filled in
     * for older entries by the migration of 2026-10-08) - before, one OR
     * EXISTS per object type, which slowed down with every entry. Deleted
     * objects still belong to their customer: "who deleted the firewall?"
     * is the question here.
     */
    protected function onlyCustomer($abfrage, int $kundeId): void
    {
        $abfrage->where('customer_id', $kundeId);
    }

    /**
     * Customer name per entry on this page.
     *
     * @return array<int, string> activity id => customer name
     */
    protected function customersOfEntries(Collection $eintraege): array
    {
        $namen = Customer::whereIn('id', $eintraege->pluck('customer_id')->filter()->unique())->pluck('name', 'id');

        return $eintraege
            ->mapWithKeys(fn ($eintrag) => [$eintrag->id => $namen[$eintrag->customer_id] ?? null])
            ->filter()->all();
    }

    /**
     * From when entries are shown.
     *
     * "today" means today, not "the last 24 hours": at 18:46 the button would
     * otherwise also show entries from 18:20 yesterday. From a week on the
     * difference is irrelevant, so there it stays rolling.
     */
    protected function grenze(): Carbon
    {
        return $this->tage === 1
            ? now()->startOfDay()
            : now()->subDays($this->tage);
    }

    /**
     * The object kinds that have entries at all.
     *
     * From the table and not from a list: things are logged that are not in
     * the trash - IP addresses, roles, credential links.
     */
    protected function types(): array
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
     * Who appears in the log and still exists.
     *
     * The table knows 114 different causer ids, most of them from sample data
     * of accounts long deleted. A select with 114 rows, 110 of which are
     * empty, helps nobody.
     */
    protected function verursacher(): array
    {
        $ids = Activity::where('causer_type', User::class)
            ->whereNotNull('causer_id')->distinct()->pluck('causer_id');

        // Kept apart by origin: a customer account with write permission
        // changes data like any technician, and that is exactly when one wants
        // to look. In a list of plain names one could not tell who belongs to
        // whom - with several customers the accounts are quickly alike.
        // Zugaenge schnell aehnlich.
        //
        // The value carries the kind with it ('user:5'), because an agent
        // token can have the same id as a user.
        $gruppen = User::whereIn('id', $ids)
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id'])
            ->groupBy(fn ($nutzer) => $nutzer->customer?->name ?? __('Mitarbeiter'))
            ->sortKeys()
            ->map(fn ($gruppe) => $gruppe->mapWithKeys(fn ($n) => ['user:'.$n->id => $n->name]))
            ->all();

        // Agents stand at the bottom and apart: "who created this?" is
        // answered differently for an agent than for a person.
        $tokenIds = Activity::where('causer_type', AgentToken::class)
            ->whereNotNull('causer_id')->distinct()->pluck('causer_id');

        $tokens = AgentToken::whereIn('id', $tokenIds)->orderBy('name')->get(['id', 'name']);

        if ($tokens->isNotEmpty()) {
            $gruppen[__('Agenten')] = $tokens
                ->mapWithKeys(fn ($t) => ['agent:'.$t->id => $t->name ?: 'Token #'.$t->id])
                ->all();
        }

        return $gruppen;
    }

    /**
     * Take the chosen causer kind and id apart ('user:5').
     *
     * @return array{0: class-string, 1: int}
     */
    protected function verursacherWahl(): array
    {
        [$art, $id] = array_pad(explode(':', $this->benutzer, 2), 2, null);

        return [$art === 'agent' ? AgentToken::class : User::class, (int) $id];
    }
}
