<?php

namespace App\Support;

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Backup;
use App\Models\Certificate;
use App\Models\Computer;
use App\Models\Concerns\HasProcurement;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\LicenseSoftware;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use App\Models\VM;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

/**
 * What the customer dashboard shows: deadlines, sites, contacts,
 * agents, the latest changes.
 *
 * Worked out once, shown twice - in the browser
 * (customer/dashboard.blade.php) and in the iOS app (Mobile\DashboardController).
 * This used to live in private methods of the CustomerController and was
 * not reachable from outside; the app would have had to rebuild it, and
 * two sums for the same deadline are two truths.
 *
 * Every tile checks the rights of the user passed in. Not those of the
 * signed-in user: when a mail goes out (see ExpiringItems) that is not
 * the same person, and nothing may show on the dashboard whose list the
 * user may not open.
 */
class CustomerDashboard
{
    /**
     * How many rows per device kind at most.
     *
     * More does not fit on a card; the full list sits behind the link.
     */
    public const JE_ART = 10;

    /** How many changes the "recently changed" tile carries. */
    public const AENDERUNGEN = 8;

    public function __construct(
        private Customer $kunde,
        private User $nutzer,
    ) {}

    /** Software licences expiring soon or expired already. */
    public function lizenzen(): Collection
    {
        if (! $this->darf('licensesoftware')) {
            return collect();
        }

        return LicenseSoftware::where('customer_id', $this->kunde->id)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(Setting::contractDeadline()))
            ->orderBy('end_date')
            ->get()
            ->map(fn ($lizenz) => $this->zeile(
                $lizenz->name,
                __('Software-Lizenz'),
                $lizenz->end_date,
                'licensesoftware',
                $lizenz->id,
            ))
            ->values();
    }

    /** SSL/TLS certificates expiring soon or expired already. */
    public function zertifikate(): Collection
    {
        if (! $this->darf('certificate')) {
            return collect();
        }

        return Certificate::where('customer_id', $this->kunde->id)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(Setting::contractDeadline()))
            ->orderBy('expiry_date')
            ->get()
            ->map(fn ($zertifikat) => $this->zeile(
                $zertifikat->name,
                __('Zertifikat'),
                $zertifikat->expiry_date,
                'certificate',
                $zertifikat->id,
            ))
            ->values();
    }

    /**
     * Hardware whose warranty is running out - across every device kind.
     *
     * The device list comes from config('custom.trashables') and is limited
     * to the models that use HasProcurement. That way there is no second
     * list to forget on the next device kind: whoever adds the trait is
     * included here automatically.
     */
    public function garantien(?int $tage = null): Collection
    {
        $tage ??= Setting::warrantyDeadline();

        return $this->procurementTypes()
            ->flatMap(function ($eintrag, $slug) use ($tage) {
                [$klasse, $bezeichnung] = $eintrag;

                return $klasse::where('customer_id', $this->kunde->id)
                    ->garantieLaeuftAb($tage)
                    ->orderBy('warranty_until')
                    ->limit(self::JE_ART)
                    ->get()
                    ->map(fn ($geraet) => $this->zeile(
                        // Phones and a few others have no name column.
                        $geraet->name ?? $geraet->serialNumber ?? '—',
                        __($bezeichnung),
                        $geraet->warranty_until,
                        $slug,
                        $geraet->id,
                        $geraet->warrantyDays(),
                    ));
            })
            ->sortBy('datum')
            ->values();
    }

    /**
     * Devices and operating systems whose support ends or has ended.
     *
     * Two sources: the support end of the hardware (eol_date through
     * HasProcurement, found like the warranties) and that of the operating
     * system on servers, VMs and computers. With the EOL deadline from the
     * settings - a server wants replacing further ahead than a licence
     * wants renewing.
     */
    public function supportEnde(): Collection
    {
        $grenze = now()->addDays(Setting::eolDeadline())->toDateString();

        $geraete = $this->procurementTypes()
            ->flatMap(fn ($eintrag, $slug) => $eintrag[0]::where('customer_id', $this->kunde->id)
                ->whereNotNull('eol_date')
                ->whereDate('eol_date', '<=', $grenze)
                ->orderBy('eol_date')
                ->limit(self::JE_ART)
                ->get()
                ->map(fn ($geraet) => $this->zeile(
                    $geraet->name ?? $geraet->serialNumber,
                    __($eintrag[1]),
                    $geraet->eol_date,
                    $slug,
                    $geraet->id,
                )));

        $systeme = collect(['server' => Server::class, 'vm' => VM::class, 'computer' => Computer::class])
            ->filter(fn ($klasse, $slug) => $this->darf($slug))
            ->flatMap(fn ($klasse, $slug) => $klasse::where('customer_id', $this->kunde->id)
                ->whereHas('operatingSystem', fn ($os) => $os->whereNotNull('eol_date')->whereDate('eol_date', '<=', $grenze))
                ->with('operatingSystem')
                ->limit(self::JE_ART)
                ->get()
                ->map(fn ($maschine) => $this->zeile(
                    $maschine->name,
                    __(config('custom.trashables')[$slug][1]).' · '.$maschine->operatingSystem->name,
                    $maschine->operatingSystem->eol_date,
                    $slug,
                    $maschine->id,
                )));

        return $geraete->concat($systeme)->sortBy('datum')->values();
    }

    /** The customer's sites. */
    public function sites(): Collection
    {
        return $this->darf('site')
            ? Site::where('customer_id', $this->kunde->id)->orderBy('name')->get()
            : collect();
    }

    /** The customer's contact persons. */
    public function contactPersons(): Collection
    {
        // By last name: contact_people has no name column, the name only
        // comes together from first_name and last_name.
        return $this->darf('contactperson')
            ? ContactPerson::where('customer_id', $this->kunde->id)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();
    }

    /**
     * The latest changes to this customer's documentation.
     *
     * Only kinds the user may list - the tile shows nothing whose list
     * stays closed to them. A deletion is a change as well.
     */
    public function letzteAenderungen(int $anzahl = self::AENDERUNGEN): Collection
    {
        // Every kind in the trash carries customer_id (the trash builds on that).
        $typen = collect(config('custom.trashables'))
            ->filter(fn ($eintrag, $slug) => $this->darf($slug));

        if ($typen->isEmpty()) {
            return collect();
        }

        $slugs = $typen->mapWithKeys(fn ($eintrag, $slug) => [$eintrag[0] => [$slug, $eintrag[1]]]);

        // customer_id on the entry itself, not "whose object is this?": an
        // OR EXISTS per object kind took 250 ms at 96,000 entries.
        return Activity::with('causer')
            ->where('customer_id', $this->kunde->id)
            ->whereIn('subject_type', $typen->pluck(0)->all())
            ->latest()
            ->limit($anzahl)
            ->get()
            ->map(fn ($eintrag) => [
                'name' => $eintrag->properties['objekt'] ?? '#'.$eintrag->subject_id,
                'art' => __($slugs[$eintrag->subject_type][1] ?? class_basename($eintrag->subject_type)),
                'ereignis' => $eintrag->event,
                'wer' => $eintrag->causer?->name,
                'wann' => $eintrag->created_at,
                'typ' => $slugs[$eintrag->subject_type][0] ?? null,
                // A deleted object lies in the trash - there is nothing to
                // highlight there and nothing to open in the app.
                'id' => $eintrag->event === 'deleted' ? null : $eintrag->subject_id,
                'url' => isset($slugs[$eintrag->subject_type]) && Route::has($slugs[$eintrag->subject_type][0].'.index')
                    ? route($slugs[$eintrag->subject_type][0].'.index', $eintrag->event === 'deleted'
                        ? [$this->kunde]
                        : [$this->kunde, 'highlight' => $eintrag->subject_id])
                    : null,
            ]);
    }

    /**
     * What needs doing about the agents: machines that stopped reporting,
     * failed runs, failed or warning backups, tokens about to expire.
     *
     *
     * null when the customer has neither agents nor tokens - then there is
     * no tile at all.
     *
     * @return Collection<int, array{name: string, art: string, text: string, schwer: bool}>|null
     */
    public function agentenWarnungen(): ?Collection
    {
        if (! Gate::forUser($this->nutzer)->allows('agent_manage')) {
            return null;
        }

        $installationen = AgentInstallation::where('customer_id', $this->kunde->id)->orderBy('hostname')->get();
        $token = AgentToken::where('customer_id', $this->kunde->id)->get();
        // Backups whose last run failed or warned, as an agent reported it.
        // Backups whose last run failed or warned, as an agent reported it.
        $backups = Backup::where('customer_id', $this->kunde->id)
            ->whereIn('last_status', ['failed', 'warning'])
            ->orderBy('name')
            ->get();

        if ($installationen->isEmpty() && $token->isEmpty()) {
            return null;
        }

        $warnungen = collect();

        foreach ($installationen as $installation) {
            if ($installation->isStale()) {
                $warnungen->push([
                    'name' => $installation->hostname,
                    'art' => __('Agent'),
                    'text' => $installation->last_seen_at
                        ? __('meldet nicht seit :zeit', ['zeit' => $installation->last_seen_at->diffForHumans(null, true)])
                        : __('meldet nicht'),
                    'schwer' => true,
                ]);
            }

            foreach ($installation->failedRoles() as $rolle) {
                $warnungen->push([
                    'name' => $installation->hostname,
                    'art' => __(AgentInstallation::availableRoles($installation->kind)[$rolle] ?? $rolle),
                    'text' => __('fehlgeschlagen'),
                    'schwer' => true,
                ]);
            }
        }

        foreach ($backups as $backup) {
            $warnungen->push([
                'name' => $backup->name,
                'art' => __('Backup').($backup->software ? ' · '.$backup->software : ''),
                'text' => __(Backup::STATUS[$backup->last_status]),
                'schwer' => $backup->last_status === 'failed',
            ]);
        }

        foreach ($token as $eines) {
            if ($eines->expires_at === null || $eines->expires_at->gt(now()->addDays(30))) {
                continue;
            }

            $warnungen->push([
                'name' => $eines->name ?: 'Token #'.$eines->id,
                'art' => __('Agent-Token'),
                'text' => $eines->isExpired()
                    ? __('abgelaufen')
                    : __('läuft ab in :tage Tagen', ['tage' => (int) now()->startOfDay()->diffInDays($eines->expires_at->copy()->startOfDay())]),
                'schwer' => $eines->isExpired(),
            ]);
        }

        return $warnungen;
    }

    /**
     * The device kinds with procurement data that the user may see.
     *
     * Warranty and support end both live in HasProcurement and query the
     * same list.
     *
     * @return Collection<string, array{0: class-string<Model>, 1: string}>
     */
    private function procurementTypes(): Collection
    {
        return collect(config('custom.trashables'))
            ->filter(fn ($eintrag, $slug) => in_array(HasProcurement::class, class_uses_recursive($eintrag[0]), true)
                && $this->darf($slug));
    }

    private function darf(string $slug): bool
    {
        return Gate::forUser($this->nutzer)->allows($slug.'_viewAny');
    }

    /**
     * One row of a deadline tile - the same shape for all four.
     *
     * "tage" is the sign the browser and the app colour by: negative means
     * expired, 0 means today. Whoever computed it already (warrantyDays())
     * passes it in.
     */
    private function zeile(
        ?string $name,
        string $art,
        Carbon|string $datum,
        string $slug,
        int $id,
        ?int $tage = null,
    ): array {
        $datum = $datum instanceof Carbon ? $datum : Carbon::parse($datum);

        return [
            'name' => $name ?: '—',
            'art' => $art,
            'datum' => $datum,
            'tage' => $tage ?? (int) now()->startOfDay()->diffInDays($datum->copy()->startOfDay(), false),
            'typ' => $slug,
            'id' => $id,
            // ?highlight= as from the global search: the list jumps to the
            // entry and marks it.
            'url' => Route::has($slug.'.index')
                ? route($slug.'.index', [$this->kunde, 'highlight' => $id])
                : null,
        ];
    }
}
