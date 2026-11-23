<?php

namespace App\Support;

use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Rules\BelongsToCustomer;
use Illuminate\Validation\Rule;

/**
 * Scan targets of a scanner - shared by the block in the browser
 * (App\Livewire\ScanTargets) and the iOS app. Either an existing target is
 * linked, or a new one is created and linked at once. Permissions stay with
 * the caller; creating also needs scantarget_create.
 */
class ScanTargetLinking
{
    public function rules(int $customerId, bool $neu): array
    {
        if (! $neu) {
            return [
                'zielId' => ['required', Rule::exists('scan_targets', 'id')
                    ->where('customer_id', $customerId)->whereNull('deleted_at')],
            ];
        }

        return [
            'name' => ['nullable', 'max:255'],
            'kind' => ['required', Rule::in(array_keys(ScanTarget::ARTEN))],
            'target' => ['required', 'max:255'],
            'login_general_id' => ['nullable', new BelongsToCustomer('login_generals', $customerId)],
        ];
    }

    public function attributeNames(): array
    {
        return [
            'zielId' => __('Scan-Ziel'), 'name' => __('Bezeichnung'), 'kind' => __('Art'),
            'target' => __('Ziel'), 'login_general_id' => __('Zugangsdaten'),
        ];
    }

    public function add(Scanner $scanner, array $daten, bool $neu): ScanTarget
    {
        // Level "read" (CustomerAccess): checked here, before anything is
        // written - this path skips the model events or touches files first.
        CustomerAccess::assertWritable((int) $scanner->customer_id);

        $ziel = $neu
            ? ScanTarget::create([
                'customer_id' => $scanner->customer_id,
                'name' => ($daten['name'] ?? null) ?: null,
                'kind' => $daten['kind'],
                'target' => $daten['target'],
                'login_general_id' => ($daten['login_general_id'] ?? null) ?: null,
            ])
            : ScanTarget::findOrFail($daten['zielId']);

        $scanner->scanTargets()->syncWithoutDetaching([$ziel->id]);

        return $ziel;
    }

    public function remove(Scanner $scanner, int $id): void
    {
        // Level "read" (CustomerAccess): checked here, before anything is
        // written - this path skips the model events or touches files first.
        CustomerAccess::assertWritable((int) $scanner->customer_id);

        $scanner->scanTargets()->detach($id);
    }
}
