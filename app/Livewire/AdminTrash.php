<?php

namespace App\Livewire;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\TracksChanges;
use App\Models\Customer;
use App\Support\StoragePath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The trash across all customers - for looking and for deleting for good.
 *
 *
 * The customer's trash shows their own entries and can bring them back. Here
 * it is the opposite: see what has piled up over the years and get rid of it.
 * Deleting is done by hand and only on what the filter shows right now - a
 * schedule removing data for good in the background would be the wrong tool
 * here.
 */
class AdminTrash extends Component
{
    use WithPagination;

    /** Only entries that have lain in the trash longer than this many days. 0 = all. */
    #[Url]
    public int $aelterAls = 0;

    /** Limit to one kind, servers only for instance. */
    #[Url]
    public string $art = '';

    /** Limit to one customer. */
    #[Url]
    public string $kunde = '';

    public bool $loeschenGefragt = false;

    /** At most this many entries are fetched per kind. */
    protected const HOECHSTENS_JE_ART = 500;

    /** True as soon as one kind has hit the cap. */
    protected bool $gekuerzt = false;

    public function mount(): void
    {
        Gate::authorize('admin_trash');
    }

    /**
     * Back to page one and close the confirmation, but only for the filters.
     *
     * Without checking the name the hook fires on every property - including
     * on deleteAsked itself, which thereby reset at once. The button then
     * simply did nothing.
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
     * Every matching entry, across the kinds.
     *
     * Deliberately merged in memory instead of by UNION: these are different
     * tables with different columns, and the numbers stay manageable - a trash
     * is no data warehouse.
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

            // Whoever does not see the cap takes the number at the top for the
            // whole stock and wonders why something is still there after
            // deleting.
            if ($gefunden->count() === static::HOECHSTENS_JE_ART) {
                $this->gekuerzt = true;
            }

            foreach ($gefunden as $eintrag) {
                $eintraege->push([
                    'slug' => $slug,
                    'art' => $bezeichnung,
                    'id' => $eintrag->id,
                    'kunde' => $eintrag->customer_id,
                    'name' => $this->displayName($eintrag),
                    'geloescht' => $eintrag->deleted_at,
                ]);
            }
        }

        return $eintraege->sortByDesc('geloescht')->values();
    }

    /**
     * What makes the entry recognisable.
     *
     * The same question as in the log and the same answer: the field list sits
     * in config/custom.php, and a model that knows better overrides
     * ueberschreibt protokollName().
     */
    protected function displayName($eintrag): string
    {
        return $eintrag->activityName() ?? '#'.$eintrag->id;
    }

    /**
     * Remove a single entry for good.
     */
    public function delete(string $slug, int $id): void
    {
        Gate::authorize('admin_trash');

        // The class comes from the allowlist, never from the request.
        $eintrag = config("custom.trashables.$slug");
        abort_unless($eintrag, 404);

        [$klasse] = $eintrag;
        $objekt = $klasse::onlyTrashed()->findOrFail($id);

        $this->endgueltig($objekt);

        $this->dispatch('notice', text: __('Eintrag endgültig gelöscht.'));
    }

    /**
     * Delete everything the filter shows right now.
     */
    public function deleteAll(): void
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

        $this->dispatch('notice', text: $anzahl === 1
            ? __('Ein Eintrag endgültig gelöscht.')
            : __(':anzahl Einträge endgültig gelöscht.', ['anzahl' => $anzahl]));
    }

    /**
     * An object together with everything hanging on it.
     *
     * The file lies outside the database and does not disappear by itself; IP
     * addresses and credentials hang on it polymorphically and would point at
     * an id that no longer exists.
     */
    protected function endgueltig($objekt): void
    {
        // Only inside the owner's folder - a path that was taken from a form
        // could point at another customer's files.
        $kunde = $objekt->customer_id ? Customer::withTrashed()->find($objekt->customer_id) : null;
        if (StoragePath::belongsTo($objekt->file_path ?? null, $kunde)) {
            Storage::disk('local')->delete($objekt->file_path);
        }

        $traits = class_uses_recursive($objekt);

        if (in_array(HasIpAddresses::class, $traits, true)) {
            $objekt->ipAddresses()->delete();
        }

        if (in_array(HasCredentials::class, $traits, true)) {
            $objekt->credentialLinks()->delete();
        }

        // Otherwise the old passwords of a device deleted for good would stay
        // behind - encrypted, but without an object they belong to and without
        // a deadline they would ever reach.
        if (in_array(TracksChanges::class, $traits, true)) {
            $objekt->passwordHistory()->delete();
        }

        $objekt->forceDelete();
    }

    public function render()
    {
        $alle = $this->sammeln();
        $proSeite = 50;

        return view('livewire.admin-trash', [
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
