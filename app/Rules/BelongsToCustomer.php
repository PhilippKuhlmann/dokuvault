<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stellt sicher, dass ein referenzierter Fremdschlüssel (z. B. site_id, network_id)
 * zum Kunden aus der Route ({customer}) gehört. Verhindert IDOR über Kundengrenzen.
 */
class BelongsToCustomer implements ValidationRule
{
    public function __construct(protected string $table) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Leere Werte überlassen wir 'required' / 'nullable'.
        if (empty($value)) {
            return;
        }

        $customer = request()->route('customer');
        $customerId = is_object($customer) ? $customer->getKey() : $customer;

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
