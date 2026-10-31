<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Attributes\Url;
use Livewire\Component;

class SearchCustomer extends Component
{
    /**
     * How many customers the list shows at most.
     *
     * The search used to fetch every hit. At 558 customers and a single letter
     * as the term that was 70 KB and 100 milliseconds - the one place in the
     * application that grows linearly with the stock (every list paginates,
     * the global search caps at 20 per object kind). Whoever is looking for a
     * customer types on anyway instead of reading five hundred names.
     * zu lesen.
     */
    private const HOECHSTENS = 50;

    #[Url]
    public $search;

    public function render()
    {
        $customers = null;
        $weitere = 0;

        if ($this->search) {
            // Fetch one more than is shown: that settles whether there are
            // more without counting a second time.
            $treffer = Customer::whereEnthaelt('name', $this->search)
                // A user tied to one customer sees only that one. The route
                // does send them to their dashboard, but Livewire does not
                // call through it - and the mere name of another customer is
                // information they are not entitled to.
                // zusteht.
                ->when(auth()->user()?->customer_id, fn ($abfrage, $eigener) => $abfrage->whereKey($eigener))
                ->orderBy('name')
                ->limit(self::HOECHSTENS + 1)
                ->get();

            $weitere = max(0, $treffer->count() - self::HOECHSTENS);
            $customers = $treffer->take(self::HOECHSTENS);

            if ($customers->isEmpty()) {
                $customers = null;
            }
        }

        return view('livewire.search-customer', [
            'customers' => $customers,
            'weitere' => $weitere,
            'gesamt' => $this->gesamt(),
        ]);
    }

    /**
     * How many customers this user can see at all.
     *
     * It stands in the masthead of the card, like the quantity on a drawing.
     * The same restriction as in the search: a user tied to one customer
     * counts only their own - otherwise the number alone would give away how
     * many others there are.
     */
    private function gesamt(): int
    {
        return Customer::query()
            ->when(auth()->user()?->customer_id, fn ($abfrage, $eigener) => $abfrage->whereKey($eigener))
            ->count();
    }
}
