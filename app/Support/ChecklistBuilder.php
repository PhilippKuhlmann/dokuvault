<?php

namespace App\Support;

use App\Models\ADUser;
use App\Models\Checklist;
use App\Models\ChecklistOption;
use App\Models\ChecklistTemplate;
use App\Models\Customer;
use App\Models\Mailbox;
use App\Models\NetworkShare;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an onboarding request or a documented AD user into checklist items.
 *
 * Onboarding: the customer's template steps plus one item per ticked network
 * drive (from the documentation) and program. Offboarding: the template steps plus what
 * the documentation knows about the person (groups, mailbox).
 *
 * Item sort numbers: template steps choose their own (100s, 900s); the
 * generated ones sit in between, so "Erster Tag" stays last.
 */
class ChecklistBuilder
{
    public const LEVELS = ['r' => 'nur lesen', 'rw' => 'lesen und schreiben'];

    /** vorname.nachname - the same scheme as the AD users in the documentation. */
    public static function username(string $firstName, string $lastName): string
    {
        $part = fn (string $value) => Str::of(Str::lower(trim($value)))
            ->replace(['ä', 'ö', 'ü', 'ß', 'ı'], ['ae', 'oe', 'ue', 'ss', 'i'])
            ->ascii()
            ->replaceMatches('/[^a-z0-9-]+/', '-')
            ->trim('-')
            ->value();

        return collect([$part($firstName), $part($lastName)])->filter()->implode('.');
    }

    /** username@first documented domain of the customer, or null without a domain. */
    public static function email(Customer $customer, string $username): ?string
    {
        $domain = $customer->domains()->orderBy('id')->value('name');

        return $domain && $username !== '' ? "{$username}@{$domain}" : null;
    }

    /**
     * Username and email of a new employee: what the form names, otherwise
     * the suggestion (vorname.nachname, @ first domain).
     *
     * @return array{0: string, 1: ?string}
     */
    public static function account(Customer $customer, array $data): array
    {
        $username = trim($data['username'] ?? '') ?: self::username($data['first_name'] ?? '', $data['last_name'] ?? '');
        $email = trim($data['email'] ?? '') ?: self::email($customer, $username);

        return [$username, $email];
    }

    /**
     * The items an onboarding request would produce - for the live preview
     * in the form and for the real start.
     *
     * @param  array{first_name?: string, last_name?: string, username?: string, email?: string, drives?: array<int|string, string>, options?: array<int, int|string>}  $data
     * @return Collection<int, array<string, mixed>>
     */
    public function onboardingItems(Customer $customer, array $data): Collection
    {
        [$username, $email] = self::account($customer, $data);
        $username = $username ?: 'vorname.nachname';

        $items = $this->templateItems($customer, 'onboarding', [
            '{username}' => $username,
            '{email}' => $email ?? '',
            '{name}' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
        ]);

        $shares = self::onboardingShares($customer)->values();

        foreach ($shares as $index => $share) {
            $level = $data['drives'][$share->id] ?? $data['drives'][(string) $share->id] ?? '';
            if (! isset(self::LEVELS[$level])) {
                continue;
            }

            $items->push([
                'section' => 'Laufwerke',
                'title' => $share->label().', '.self::LEVELS[$level],
                'action' => $share->groupFor($level) ? 'add_group' : null,
                'reference' => $share->groupFor($level)?->name,
                'hint' => $share->path,
                'assignee' => 'it',
                'due_offset' => -5,
                'sort' => 400 + $index,
                'needs_approval' => $share->sensitive,
            ]);
        }

        $options = $customer->checklistOptions()->active()->ordered()->with('adGroup')->get()->keyBy('id');

        foreach (array_unique(array_map('intval', $data['options'] ?? [])) as $id) {
            $option = $options->get($id);
            if (! $option) {
                continue;
            }

            $items->push([
                'section' => 'Programme und Zugänge',
                'title' => $option->assignee === 'customer'
                    ? "{$option->name}: Zugang beantragen"
                    : "{$option->name}: Zugang einrichten",
                'action' => $option->adGroup ? 'add_group' : null,
                'reference' => $option->adGroup?->name,
                'hint' => $option->detail,
                'assignee' => $option->assignee,
                'due_offset' => -5,
                'sort' => 500 + $option->sort,
                'needs_approval' => $option->needs_approval,
            ]);
        }

        return $items->sortBy('sort')->values();
    }

    /**
     * @param  array{first_name: string, last_name: string, reference_date: string, username?: ?string, email?: ?string, note?: ?string, drives?: array, options?: array}  $data
     */
    public function startOnboarding(Customer $customer, array $data, ?User $by): Checklist
    {
        [$username, $email] = self::account($customer, $data);

        return DB::transaction(function () use ($customer, $data, $by, $username, $email) {
            $checklist = $customer->checklists()->create([
                'type' => 'onboarding',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'reference_date' => $data['reference_date'],
                'username' => $username,
                'email' => $email,
                'request' => [
                    'drives' => array_filter($data['drives'] ?? [], fn ($level) => isset(self::LEVELS[$level])),
                    'options' => array_values(array_map('intval', $data['options'] ?? [])),
                ],
                'note' => $data['note'] ?? null,
                'requested_by' => $by?->id,
            ]);

            $checklist->items()->createMany($this->onboardingItems($customer, $data)->all());

            return $checklist;
        });
    }

    /** Offboarding for a documented AD user: template steps plus groups and mailbox. */
    public function startOffboarding(Customer $customer, ADUser $adUser, string $lastDay, ?User $by, ?string $note = null): Checklist
    {
        abort_unless($adUser->customer_id === $customer->id, 404);

        $items = $this->templateItems($customer, 'offboarding', [
            '{username}' => $adUser->username ?? '',
            '{email}' => $adUser->email ?? '',
            '{name}' => trim("{$adUser->firstName} {$adUser->lastName}"),
        ]);

        foreach ($adUser->groups as $index => $group) {
            $items->push([
                'section' => 'Gruppen',
                'title' => "Aus Gruppe {$group->name} entfernen",
                'action' => 'remove_group',
                'reference' => $group->name,
                'hint' => $group->description,
                'assignee' => 'it',
                'due_offset' => 0,
                'sort' => 200 + $index,
                'needs_approval' => false,
            ]);
        }

        $mailbox = $adUser->email
            ? Mailbox::where('customer_id', $customer->id)->whereRaw('LOWER(mailAdress) = ?', [Str::lower($adUser->email)])->first()
            : null;

        if ($mailbox) {
            $items->push([
                'section' => 'E-Mail',
                'title' => "Postfach {$mailbox->mailAdress} umwandeln oder Weiterleitung einrichten",
                'action' => null,
                'reference' => $mailbox->mailAdress,
                'hint' => 'freigegebenes Postfach für Vorgesetzte, Lizenz freigeben',
                'assignee' => 'it',
                'due_offset' => 1,
                'sort' => 300,
                'needs_approval' => false,
            ]);
        }

        return DB::transaction(function () use ($customer, $adUser, $lastDay, $by, $note, $items) {
            $checklist = $customer->checklists()->create([
                'type' => 'offboarding',
                'first_name' => $adUser->firstName ?: $adUser->username,
                'last_name' => $adUser->lastName ?? '',
                'reference_date' => $lastDay,
                'username' => $adUser->username,
                'email' => $adUser->email,
                'ad_user_id' => $adUser->id,
                'note' => $note,
                'requested_by' => $by?->id,
            ]);

            $checklist->items()->createMany($items->sortBy('sort')->values()->all());

            return $checklist;
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function templateItems(Customer $customer, string $type, array $placeholders): Collection
    {
        return ChecklistTemplate::ofType($type)->itemsFor($customer)->map(fn ($item) => [
            'section' => $item->section,
            'title' => strtr($item->title, $placeholders),
            'action' => $item->action,
            'reference' => null,
            'hint' => null,
            'assignee' => $item->assignee,
            'due_offset' => $item->due_offset,
            'sort' => $item->sort,
            'needs_approval' => false,
        ])->values();
    }

    /** Groups items by section, keeping the order of their first appearance. */
    public static function sections(iterable $items): Collection
    {
        return collect($items)->groupBy(fn ($item) => is_array($item) ? $item['section'] : $item->section);
    }

    /** The documented network drives a customer offers in the onboarding form. */
    public static function onboardingShares(Customer $customer): Collection
    {
        return NetworkShare::where('customer_id', $customer->id)->where('in_onboarding_form', true)
            ->with(['readGroup', 'writeGroup'])->orderBy('drive_letter')->orderBy('name')->get();
    }

    /** The options of a customer grouped by kind, for forms and settings. */
    public static function optionsByKind(Customer $customer, bool $activeOnly = true): Collection
    {
        $query = $customer->checklistOptions()->ordered();
        if ($activeOnly) {
            $query->active();
        }

        return collect(ChecklistOption::KINDS)->mapWithKeys(fn ($kind) => [$kind => collect()])
            ->merge($query->get()->groupBy('kind'));
    }
}
