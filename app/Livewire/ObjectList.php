<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Site;
use App\Support\AutoCheck;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The list for one kind from config/forms.php - as Livewire, so an entry
 * created in the modal appears at once instead of only after a reload.
 *
 * The look of the individual card stays with the kind: it still sits in
 * resources/views/<typ>/_karte.blade.php. A generic card would have made every
 * list look the same, and here it is precisely the differences that carry the
 * information.
 */
class ObjectList extends Component
{
    use ScopedToCustomer;
    use WithPagination;

    #[Locked]
    public string $typ;

    #[Locked]
    public int $customerId;

    #[Url]
    public string $search = '';

    /**
     * Filter values, kept by filter name. Which filters exist is said by the
     * kind in config/forms.php - a licence has a term, a printer does not.
     * nicht.
     */
    #[Url]
    public array $filter = [];

    /** A key from the kind's 'sortierungen'; empty means the first of them. */
    #[Url(except: '')]
    public string $sortierung = '';

    /**
     * An entry jumped to from the global search: its card gets highlighted and
     * scrolled into view. Not a #[Url] - the value arrives once via ?highlight=
     * from the search and should not stick in the address bar. Any own
     * interaction (search/filter/sort) clears it.
     */
    public ?int $highlight = null;

    public function mount(string $typ, Customer $customer): void
    {
        abort_unless(array_key_exists($typ, config('forms')), 404);

        Gate::authorize($typ.'_viewAny');
        $this->onlyOwnCustomer($customer->id);

        $this->typ = $typ;
        $this->customerId = $customer->id;

        // From the global search: jump to the matched entry. The page is computed
        // once here (not in render()) so later pagination clicks don't keep
        // throwing the user back onto the match's page.
        $ziel = request()->integer('highlight');
        if ($ziel) {
            $this->highlight = $ziel;
            $this->jumpToEntryPage($ziel);
        }
    }

    /**
     * Set the pagination page so the searched entry is on it.
     *
     * Deliberately without the site filter, search and filters: the match comes
     * from the global search and should be found regardless of the site chosen
     * in the sidebar - just as render() then also shows it.
     */
    protected function jumpToEntryPage(int $id): void
    {
        $klasse = config('forms.'.$this->typ.'.model');

        $abfrage = $klasse::where('customer_id', $this->customerId);
        $this->sortierungAnwenden($abfrage);

        $position = $abfrage->pluck('id')->search($id);

        if ($position !== false) {
            $this->setPage(intdiv($position, Setting::seiteListe()) + 1);
        }
    }

    public function updatedSearch(): void
    {
        $this->highlight = null;
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->highlight = null;
        $this->resetPage();
    }

    public function updatedSortierung(): void
    {
        $this->highlight = null;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filter', 'sortierung', 'highlight']);
        $this->resetPage();
    }

    /**
     * The filters of this kind, or an empty list.
     *
     * With a relation filter the choices come from this customer's stock, not
     * from the whole catalog: an operating system without a licence would be a
     * row that never finds anything.
     */
    public function filterDefinition(): array
    {
        $klasse = config('forms.'.$this->typ.'.model');

        return collect(config('forms.'.$this->typ.'.filter', []))
            ->map(function (array $def) use ($klasse) {
                if (($def['typ'] ?? '') !== 'beziehung') {
                    return $def;
                }

                $ids = $klasse::where('customer_id', $this->customerId)
                    ->whereNotNull($def['feld'])->distinct()->pluck($def['feld']);

                $def['optionen'] = $def['quelle']::whereIn('id', $ids)
                    ->orderBy($def['anzeige'])->pluck($def['anzeige'], 'id')->all();

                return $def;
            })->all();
    }

    /** The sort orders of this kind, or an empty list. */
    public function sortierungen(): array
    {
        return config('forms.'.$this->typ.'.sortierungen', []);
    }

    /** Whether the model carries a site_id column - checked once per table. */
    private function tableHasSite(string $klasse): bool
    {
        static $cache = [];

        $tabelle = (new $klasse)->getTable();

        return $cache[$tabelle] ??= Schema::hasColumn($tabelle, 'site_id');
    }

    /** True as soon as something is limited - for the reset button. */
    public function gefiltert(): bool
    {
        return $this->search !== ''
            || $this->sortierung !== ''
            || collect($this->filter)->contains(fn ($w) => $w !== '' && $w !== null);
    }

    /** Re-render after saving in the modal - only for the own kind. */
    #[On('object-saved')]
    public function reloaded(string $typ): void
    {
        if ($typ === $this->typ) {
            $this->resetPage();
        }
    }

    /**
     * IP addresses and credentials are maintained in blocks of their own in
     * the modal. The list shows them in its columns and therefore has to
     * redraw on their changes as well - an empty body is enough, Livewire
     * re-renders after every call anyway.
     */
    #[On('device-changed')]
    public function deviceChanged(): void {}

    /**
     * "Check now" on a domain or certificate card - the same check the
     * nightly domains:check runs, for one entry, without waiting a day.
     */
    public function check(int $id): void
    {
        abort_unless(in_array($this->typ, ['domain', 'certificate'], true), 404);
        Gate::authorize($this->typ.'_update');
        $this->onlyOwnCustomer($this->customerId);

        $klasse = config('forms.'.$this->typ.'.model');
        $eintrag = $klasse::where('customer_id', $this->customerId)->findOrFail($id);

        // DNS, RDAP and TLS together can take longer than PHP's default 30
        // seconds; the check has its own budget below this. Only ever
        // raised: set_time_limit() counts for the whole process, and 0 (the
        // CLI, the test suite) means no limit - lowering that to 90 seconds
        // broke the CI run halfway through the suite.
        $limit = (int) ini_get('max_execution_time');
        if ($limit !== 0 && $limit < 90) {
            set_time_limit(90);
        }

        $this->typ === 'domain'
            ? app(AutoCheck::class)->checkDomain($eintrag)
            // Customer accounts only reach public targets - see CertificateCheck.
            : app(AutoCheck::class)->checkCertificate($eintrag, auth()->user()->hasCustomer());

        $this->dispatch('notice', text: $eintrag->check_error
            ? __('Prüfung fehlgeschlagen: :fehler', ['fehler' => $eintrag->check_error])
            : __('Geprüft und aktualisiert.'));
    }

    public function render()
    {
        $einstellung = config('forms.'.$this->typ);
        $klasse = $einstellung['model'];

        $abfrage = $klasse::where('customer_id', $this->customerId);

        // Site filter - the same rule as Controller::getFilteredQuery, except
        // that it was missing here: since the lists run through this Livewire
        // component the site chosen in the sidebar was no longer applied and
        // the list showed every device of the customer. Only takes hold when a
        // valid site of this customer is chosen and the model carries one at all.
        // When jumping in from the global search, suspend the site filter:
        // otherwise a different site chosen in the sidebar would hide the very
        // entry we wanted to jump to.
        $site = session()->get('site');

        if (! $this->highlight && $site && $site !== 'all'
            && $this->tableHasSite($klasse)
            && Site::where('customer_id', $this->customerId)->whereKey($site)->exists()) {
            $abfrage->where('site_id', $site);
        }

        // The same preloading as in the controllers (see
        // Controller::preloadCredentials): without it a page with 25 devices
        // costs about a hundred queries instead of eight. The conditions are
        // the same - credentials and addresses only where the model carries
        // them, or a list without both counts two queries too many.
        $traits = class_uses_recursive($klasse);

        if (in_array(HasCredentials::class, $traits, true)) {
            $abfrage->with('credentialLinks.login');
        }

        if (in_array(HasIpAddresses::class, $traits, true)) {
            $abfrage->with('ipAddresses.network');
        }

        foreach (['rackItem.rack', 'operatingSystem', 'site'] as $relation) {
            if (method_exists($klasse, explode('.', $relation)[0])) {
                $abfrage->with($relation);
            }
        }

        if (! empty($einstellung['mitladen'])) {
            $abfrage->with($einstellung['mitladen']);
        }

        if ($this->search !== '') {
            // The escaping was already here, but without an ESCAPE clause: on
            // MySQL that went well, on SQLite a term with an underscore found
            // nothing at all. whereEnthaelt brings both along.
            $abfrage->whereEnthaelt($einstellung['suchfelder'], $this->search);
        }

        $this->filterAnwenden($abfrage);
        $this->sortierungAnwenden($abfrage);

        return view('livewire.object-list', [
            'eintraege' => $abfrage->paginate(Setting::seiteListe()),
            'filterDefinition' => $this->filterDefinition(),
            'sortierungen' => $this->sortierungen(),
            'gefiltert' => $this->gefiltert(),
            'customer' => Customer::findOrFail($this->customerId),
            'einzahl' => $einstellung['einzahl'],
            'highlight' => $this->highlight,
        ]);
    }

    /**
     * Put the filters of the kind onto the query.
     *
     * Three sorts are enough for the stock: a date ("expiring"), a fixed value
     * list (subscription) and a relation (operating system). Which of them a
     * kind offers stands with it in config/forms.php.
     */
    protected function filterAnwenden($abfrage): void
    {
        foreach ($this->filterDefinition() as $definition) {
            $wert = $this->filter[$definition['name']] ?? '';

            if ($wert === '' || $wert === null) {
                continue;
            }

            match ($definition['typ']) {
                'ablauf' => $this->ablaufFiltern($abfrage, $definition['feld'], $wert),
                default => $abfrage->where($definition['feld'], $wert),
            };
        }
    }

    /**
     * Nach Ablaufdatum einschraenken.
     *
     * "offen" instead of "laeuft": a licence without an end date does not
     * expire and counts among the unproblematic ones - it would otherwise fall
     */
    protected function ablaufFiltern($abfrage, string $feld, string $wert): void
    {
        match ($wert) {
            'abgelaufen' => $abfrage->whereNotNull($feld)->whereDate($feld, '<', now()),
            'offen' => $abfrage->where(fn ($a) => $a->whereNull($feld)->orWhereDate($feld, '>=', now())),
            default => $abfrage->whereNotNull($feld)
                ->whereDate($feld, '>=', now())
                ->whereDate($feld, '<=', now()->addDays((int) $wert)),
        };
    }

    /**
     * Sort by what the kind offers.
     *
     * Without a choice it stays with "most recently created first" - as it was
     * before this select existed.
     *
     * With a date, empty values go last: a licence without an end date is not
     * the one expiring next.
     */
    protected function sortierungAnwenden($abfrage): void
    {
        $sortierungen = $this->sortierungen();
        // Empty (or an unknown key from the address bar): the first sort
        // order of the type - the one the field shows.
        $gewaehlt = $sortierungen[$this->sortierung] ?? (reset($sortierungen) ?: null);

        if (! $gewaehlt) {
            $abfrage->latest();
        } else {
            [$beschriftung, $spalte, $richtung] = $gewaehlt;

            // Empty values last: dates as before, other columns on request
            // (a fourth entry true - network drives without a letter).
            if (str_ends_with($spalte, '_date') || str_ends_with($spalte, '_at') || ($gewaehlt[3] ?? false)) {
                $abfrage->orderByRaw($spalte.' IS NULL');
            }

            $abfrage->orderBy($spalte, $richtung);
        }

        // Ties broken by id. An agent creates fifty VMs within one second;
        // with equal created_at the database may order them differently per
        // query - jumpToEntryPage() computed page 2, the page itself showed
        // other VMs, and the match from the global search was not there.
        $abfrage->orderByDesc($abfrage->getModel()->getQualifiedKeyName());
    }
}
