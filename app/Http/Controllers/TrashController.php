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

        foreach (config('custom.trashables') as $slug => [$class, $label]) {
            $trashed = $class::onlyTrashed()
                ->where('customer_id', $customer->id)
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

        return view('trash.index', compact('customer', 'items'));
    }

    public function restore(Customer $customer, string $type, int $id)
    {
        Gate::authorize('see_hidden');

        // Klasse ausschließlich aus der Config-Whitelist auflösen — nie aus User-Input.
        $entry = config("custom.trashables.$type");
        abort_unless($entry, 404);
        [$class] = $entry;

        // customer_id-Scoping verhindert Cross-Tenant-Restore (IDOR).
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
