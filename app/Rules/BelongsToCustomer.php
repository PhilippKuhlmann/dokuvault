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
    /**
     * Ohne $customerId kommt der Kunde aus der Route. Livewire-Komponenten
     * haben den Parameter nicht - dort laeuft jede Anfrage gegen
     * livewire.update, der Kunde waere null und die Pruefung schlueg immer
     * fehl. Sie geben ihn deshalb mit.
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
