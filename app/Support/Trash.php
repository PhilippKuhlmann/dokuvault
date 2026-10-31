<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * A customer's trash: what was deleted and what can be brought
 * back.
 *
 * Worked out once, shown twice - in the browser (TrashController) and
 * in the iOS app (Mobile\TrashController). The kinds come from
 * config('custom.trashables'), never from the input: the type in the
 * URL is a key of that list, otherwise 404.
 */
class Trash
{
    /**
     * How many entries per kind at most.
     *
     * What gets cut off is reported by `gekuerzt` - cutting silently
     * reads like "there is no more".
     */
    public const JE_ART = 100;

    /** What makes a deleted object recognisable, column by column. */
    private const NAMENSSPALTEN = ['name', 'ssid', 'username', 'mailAdress', 'domain', 'provider', 'description', 'host'];

    public function __construct(
        private Customer $kunde,
        private User $nutzer,
    ) {}

    /** Whether the signed-in user may look into the trash at all. */
    public function darfSehen(): bool
    {
        return Gate::forUser($this->nutzer)->allows('see_hidden');
    }

    /**
     * What lies in the trash, newest first.
     *
     * Only kinds this user may see - otherwise names of objects they
     * are not meant to know about would show up here.
     *
     * @return Collection<int, array{typ: string, titel: string, name: string, geloescht_am: mixed, id: int, darf_wiederherstellen: bool}>
     */
    public function eintraege(): Collection
    {
        $eintraege = collect();

        foreach ($this->types() as $slug => [$klasse, $bezeichnung]) {
            $geloescht = $klasse::onlyTrashed()
                ->where('customer_id', $this->kunde->id)
                ->orderByDesc('deleted_at')
                ->limit(self::JE_ART)
                ->get();

            foreach ($geloescht as $objekt) {
                $eintraege->push([
                    'typ' => $slug,
                    'titel' => __($bezeichnung),
                    'name' => $this->name($objekt),
                    'geloescht_am' => $objekt->deleted_at,
                    'id' => $objekt->id,
                    'darf_wiederherstellen' => $this->mayRestore($slug),
                ]);
            }
        }

        return $eintraege->sortByDesc('geloescht_am')->values();
    }

    /**
     * Which kinds hold more than the list shows.
     *
     * @return array<string, int> label => how many there really are
     */
    public function gekuerzt(): array
    {
        $gekuerzt = [];

        foreach ($this->types() as $slug => [$klasse, $bezeichnung]) {
            $vorhanden = $klasse::onlyTrashed()->where('customer_id', $this->kunde->id)->count();

            if ($vorhanden > self::JE_ART) {
                $gekuerzt[$bezeichnung] = $vorhanden;
            }
        }

        return $gekuerzt;
    }

    /**
     * Bring an object back.
     *
     * That needs the right to delete, not just the right to look into
     * the trash: restoring undoes a deletion.
     */
    public function restore(string $typ, int $id): Model
    {
        // The class comes from the configured list, never from the
        // input.
        $eintrag = config("custom.trashables.$typ");
        abort_unless($eintrag, 404);
        [$klasse] = $eintrag;

        Gate::forUser($this->nutzer)->authorize($typ.'_delete');

        // Pinned to the customer: no restoring across customers (IDOR).
        $objekt = $klasse::onlyTrashed()
            ->where('customer_id', $this->kunde->id)
            ->findOrFail($id);

        $objekt->restore();

        return $objekt;
    }

    public function mayRestore(string $typ): bool
    {
        return Gate::forUser($this->nutzer)->allows($typ.'_delete');
    }

    /**
     * The kinds this user may see.
     *
     * @return array<string, array{0: class-string<Model>, 1: string}>
     */
    private function types(): array
    {
        return array_filter(
            config('custom.trashables'),
            fn ($eintrag, $slug) => Gate::forUser($this->nutzer)->allows($slug.'_viewAny'),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function name(Model $objekt): string
    {
        foreach (self::NAMENSSPALTEN as $spalte) {
            $wert = $objekt->getAttribute($spalte);

            if (is_string($wert) && $wert !== '') {
                return $wert;
            }
        }

        return '#'.$objekt->getKey();
    }
}
