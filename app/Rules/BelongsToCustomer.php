<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes sure that a referenced foreign key (site_id, network_id and the like)
 * belongs to the customer from the route ({customer}). Prevents IDOR across customers.
 */
class BelongsToCustomer implements ValidationRule
{
    /**
     * Without $customerId the customer comes from the route. Livewire
     * components do not have the parameter - there every request runs against
     * livewire.update, the customer would be null and the check would always
     * fail. They therefore pass it in.
     */
    public function __construct(protected string $table, protected ?int $customerId = null) {}

    public function tabelle(): string
    {
        return $this->table;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Leere Werte überlassen wir 'required' / 'nullable'.
        if (empty($value)) {
            return;
        }

        if ($this->customerId !== null) {
            $customerId = $this->customerId;
        } else {
            $customer = request()->route('customer');
            $customerId = is_object($customer) ? $customer->getKey() : $customer;
        }

        $query = DB::table($this->table)
            ->where('id', $value)
            ->where('customer_id', $customerId);

        if (Schema::hasColumn($this->table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (! $query->exists()) {
            $fail(__('Die Auswahl für :attribute gehört nicht zu diesem Kunden.'));
        }
    }
}
