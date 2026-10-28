<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Support\Facades\Gate;

class TrashController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('see_hidden');

        $items = collect();

        // At most a hundred entries per kind. When cut, the page says so - a
        // silent cut reads like "there is no more".
        $gekuerzt = [];

        foreach (config('custom.trashables') as $slug => [$class, $label]) {
            // Only kinds this user may see at all: the trash would otherwise
            // list names of objects they have no right to know about.
            if (Gate::denies($slug.'_viewAny')) {
                continue;
            }

            $vorhanden = $class::onlyTrashed()->where('customer_id', $customer->id)->count();

            if ($vorhanden > 100) {
                $gekuerzt[$label] = $vorhanden;
            }

            $trashed = $class::onlyTrashed()
                ->where('customer_id', $customer->id)
                ->orderByDesc('deleted_at')
                ->limit(100)
                ->get();

            foreach ($trashed as $item) {
                $items->push([
                    'type' => $slug,
                    'label' => $label,
                    'name' => $this->displayName($item),
                    'deleted_at' => $item->deleted_at,
                    'id' => $item->id,
                ]);
            }
        }

        $items = $items->sortByDesc('deleted_at')->values();

        return view('trash.index', compact('customer', 'items', 'gekuerzt'));
    }

    public function restore(Customer $customer, string $type, int $id)
    {
        Gate::authorize('see_hidden');

        // The class only ever comes from the config allowlist, never from input.
        $entry = config("custom.trashables.$type");
        abort_unless($entry, 404);
        [$class] = $entry;

        // Restoring undoes a delete - so it takes the right to delete that
        // kind, not only the right to look into the trash.
        Gate::authorize($type.'_delete');

        // Scoped to the customer: no restore across tenants (IDOR).
        $item = $class::onlyTrashed()
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        $item->restore();

        return redirect(route('trash.index', $customer))
            ->with('success', 'Objekt wiederhergestellt.');
    }

    protected function displayName($item): string
    {
        foreach (['name', 'ssid', 'username', 'mailAdress', 'domain', 'provider', 'description', 'host'] as $field) {
            $value = $item->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#'.$item->id;
    }
}
