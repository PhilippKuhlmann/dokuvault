<?php

namespace App\Support;

use App\Models\AgentInstallation;
use App\Models\Certificate;
use App\Models\Concerns\HatBeschaffung;
use App\Models\Domain;
use App\Models\Firewall;
use App\Models\LicenseSoftware;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Everything that expires soon or already has, as one user may see it.
 *
 * "May see" twice over: a user bound to a customer gets only that
 * customer, and every kind is checked against the user's role. A mail must
 * not tell someone about certificates the application would not show them.
 *
 * The warning periods are the ones under Admin -> Fristen, the same numbers
 * the customer dashboard uses - a mail that warns on a different day than
 * the dashboard would be a second truth.
 */
class ExpiringItems
{
    public const SOON = 'soon';

    public const EXPIRED = 'expired';

    /** An agent that stopped reporting - nothing expires, but someone has to look. */
    public const SILENT = 'silent';

    /**
     * @param  array<int, string>  $kinds  from Setting::EXPIRY_KINDS
     */
    public static function forUser(User $user, array $kinds): Collection
    {
        $gate = Gate::forUser($user);
        $items = collect();

        $contracts = [
            'certificate' => [Certificate::class, 'expiry_date', __('Zertifikat')],
            'domain' => [Domain::class, 'expiry_date', __('Domain')],
            'licensesoftware' => [LicenseSoftware::class, 'end_date', __('Software-Lizenz')],
            'firewall' => [Firewall::class, 'subscription_until', __('Firewall-Subscription')],
        ];

        foreach ($contracts as $slug => [$class, $column, $label]) {
            if (! in_array($slug, $kinds, true) || ! $gate->allows($slug.'_viewAny')) {
                continue;
            }

            $query = $class::query()->with('customer')
                ->whereNotNull($column)
                ->whereDate($column, '<=', now()->addDays(Setting::fristVertraege()));

            $items = $items->merge(self::rows(self::scoped($query, $user)->get(), 'contract', $slug, $label, $column));
        }

        if (in_array('warranty', $kinds, true)) {
            foreach (config('custom.trashables') as $slug => [$class, $label]) {
                if (! in_array(HatBeschaffung::class, class_uses_recursive($class), true)
                    || ! $gate->allows($slug.'_viewAny')) {
                    continue;
                }

                $query = $class::query()->with('customer')->garantieLaeuftAb();

                $items = $items->merge(self::rows(self::scoped($query, $user)->get(), 'warranty', $slug,
                    __('Garantie').' · '.__($label), 'warranty_until'));
            }
        }

        if (in_array('agent', $kinds, true) && $gate->allows('agent_manage')) {
            $items = $items->merge(self::silentAgents($user));
        }

        return $items->sortBy([['customer', 'asc'], ['date', 'asc']])->values();
    }

    /**
     * Agents that have not reported for longer than the threshold under
     * Admin -> Agenten - the same rule as the warning on the dashboard.
     *
     * The date is the last contact: it is part of what NotifyExpiry
     * remembers, so an agent is mailed once when it falls silent and again
     * only after it reported in between and fell silent anew.
     */
    private static function silentAgents(User $user): Collection
    {
        $query = AgentInstallation::query()->with('customer')
            ->where(fn ($q) => $q->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subHours(Setting::agentStillStunden())));

        return self::scoped($query, $user)->get()
            ->filter(fn (AgentInstallation $a) => $a->customer)
            ->map(function (AgentInstallation $a) {
                $seen = $a->last_seen_at ?? $a->created_at;

                return [
                    'kind' => 'agent',
                    'label' => __('Agent'),
                    'name' => $a->hostname,
                    'customer' => $a->customer->name,
                    'date' => $seen->copy()->startOfDay(),
                    'seen' => $a->last_seen_at,
                    'days' => (int) $seen->copy()->startOfDay()->diffInDays(now()->startOfDay(), false) * -1,
                    'stage' => self::SILENT,
                    'url' => route('agent.index', $a->customer),
                    'subject_type' => $a->getMorphClass(),
                    'subject_id' => $a->id,
                ];
            })
            ->values();
    }

    private static function scoped($query, User $user)
    {
        return $user->customer_id ? $query->where('customer_id', $user->customer_id) : $query;
    }

    private static function rows(Collection $models, string $kind, string $slug, string $label, string $column): Collection
    {
        return $models->filter(fn ($m) => $m->customer)->map(function ($m) use ($kind, $slug, $label, $column) {
            // Not every model casts its date column.
            $date = Carbon::parse($m->$column)->startOfDay();
            $days = (int) now()->startOfDay()->diffInDays($date, false);

            return [
                'kind' => $kind,
                'label' => $label,
                // Phones and a few others have no name column.
                'name' => $m->name ?? $m->serialNumber ?? '—',
                'customer' => $m->customer->name,
                'date' => $date,
                'days' => $days,
                'stage' => $days < 0 ? self::EXPIRED : self::SOON,
                'url' => route($slug.'.index', [$m->customer, 'highlight' => $m->id]),
                'subject_type' => $m->getMorphClass(),
                'subject_id' => $m->id,
            ];
        });
    }
}
