<?php

namespace App\Support;

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Backup;
use App\Models\Certificate;
use App\Models\Computer;
use App\Models\Concerns\HatBeschaffung;
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
 * Was auf dem Kunden-Dashboard steht: Fristen, Standorte, Ansprechpartner,
 * Agenten, die letzten Aenderungen.
 *
 * Einmal gerechnet, zweimal gezeigt - im Browser
 * (customer/dashboard.blade.php) und in der iOS-App (Mobile\DashboardController).
 * Vorher lag das in privaten Methoden des CustomerControllers und war von
 * aussen nicht erreichbar; die App haette es nachbauen muessen, und zwei
 * Rechnungen fuer dieselbe Frist sind zwei Wahrheiten.
 *
 * Jede Kachel prueft die Rechte des uebergebenen Nutzers. Nicht die des
 * Angemeldeten: Beim Versand einer Mail (siehe ExpiringItems) ist das nicht
 * derselbe, und auf dem Dashboard darf nichts stehen, dessen Liste der
 * Nutzer nicht oeffnen darf.
 */
class Kundenuebersicht
{
    /**
     * Wie viele Zeilen je Geraeteart hoechstens.
     *
     * Mehr passt nicht auf eine Karte; die vollstaendige Liste steht hinter
     * dem Verweis.
     */
    public const JE_ART = 10;

    /** Wie viele Aenderungen die Kachel "Zuletzt geaendert" fuehrt. */
    public const AENDERUNGEN = 8;

    public function __construct(
        private Customer $kunde,
        private User $nutzer,
    ) {}

    /** Software-Lizenzen, die bald ablaufen oder schon abgelaufen sind. */
    public function lizenzen(): Collection
    {
        if (! $this->darf('licensesoftware')) {
            return collect();
        }

        return LicenseSoftware::where('customer_id', $this->kunde->id)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(Setting::fristVertraege()))
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

    /** SSL/TLS-Zertifikate, die bald ablaufen oder schon abgelaufen sind. */
    public function zertifikate(): Collection
    {
        if (! $this->darf('certificate')) {
            return collect();
        }

        return Certificate::where('customer_id', $this->kunde->id)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(Setting::fristVertraege()))
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
     * Hardware, deren Garantie ablaeuft - ueber alle Geraetearten hinweg.
     *
     * Die Geraeteliste kommt aus config('custom.trashables') und wird auf die
     * Models eingeschraenkt, die HatBeschaffung einbinden. Damit gibt es keine
     * zweite Liste, die man beim naechsten Geraetetyp vergessen kann: Wer den
     * Trait einbaut, ist hier automatisch dabei.
     */
    public function garantien(?int $tage = null): Collection
    {
        $tage ??= Setting::fristGarantie();

        return $this->beschaffungsarten()
            ->flatMap(function ($eintrag, $slug) use ($tage) {
                [$klasse, $bezeichnung] = $eintrag;

                return $klasse::where('customer_id', $this->kunde->id)
                    ->garantieLaeuftAb($tage)
                    ->orderBy('warranty_until')
                    ->limit(self::JE_ART)
                    ->get()
                    ->map(fn ($geraet) => $this->zeile(
                        // Telefone und einige andere haben keine name-Spalte.
                        $geraet->name ?? $geraet->serialNumber ?? '—',
                        __($bezeichnung),
                        $geraet->warranty_until,
                        $slug,
                        $geraet->id,
                        $geraet->garantieTage(),
                    ));
            })
            ->sortBy('datum')
            ->values();
    }

    /**
     * Geraete und Betriebssysteme, deren Support endet oder geendet hat.
     *
     * Zwei Quellen: das Support-Ende der Hardware (eol_date ueber
     * HatBeschaffung, gefunden wie die Garantien) und das des Betriebssystems
     * auf Servern, VMs und Computern. Mit der EOL-Frist aus den
     * Einstellungen - ein Server will laenger vorher ersetzt werden, als eine
     * Lizenz verlaengert.
     */
    public function supportEnde(): Collection
    {
        $grenze = now()->addDays(Setting::fristEol())->toDateString();

        $geraete = $this->beschaffungsarten()
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

    /** Die Standorte des Kunden. */
    public function standorte(): Collection
    {
        return $this->darf('site')
            ? Site::where('customer_id', $this->kunde->id)->orderBy('name')->get()
            : collect();
    }

    /** Die Ansprechpartner des Kunden. */
    public function ansprechpartner(): Collection
    {
        // Nach Nachnamen: contact_people hat keine name-Spalte, der Name
        // entsteht erst aus first_name und last_name.
        return $this->darf('contactperson')
            ? ContactPerson::where('customer_id', $this->kunde->id)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();
    }

    /**
     * Die letzten Aenderungen an der Dokumentation dieses Kunden.
     *
     * Nur Arten, die der Nutzer auflisten darf - die Kachel zeigt nichts,
     * dessen Liste ihm verschlossen bleibt. Ein Loeschen ist auch eine
     * Aenderung.
     */
    public function letzteAenderungen(int $anzahl = self::AENDERUNGEN): Collection
    {
        // Jede Art im Papierkorb traegt customer_id (darauf baut er auf).
        $typen = collect(config('custom.trashables'))
            ->filter(fn ($eintrag, $slug) => $this->darf($slug));

        if ($typen->isEmpty()) {
            return collect();
        }

        $slugs = $typen->mapWithKeys(fn ($eintrag, $slug) => [$eintrag[0] => [$slug, $eintrag[1]]]);

        // customer_id am Eintrag selbst, nicht "wessen Objekt ist das?": ein
        // OR EXISTS je Objektart brauchte 250 ms bei 96.000 Eintraegen.
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
                // Ein gelöschtes Objekt liegt im Papierkorb - dort ist nichts
                // zu markieren und in der App nichts zu oeffnen.
                'id' => $eintrag->event === 'deleted' ? null : $eintrag->subject_id,
                'url' => isset($slugs[$eintrag->subject_type]) && Route::has($slugs[$eintrag->subject_type][0].'.index')
                    ? route($slugs[$eintrag->subject_type][0].'.index', $eintrag->event === 'deleted'
                        ? [$this->kunde]
                        : [$this->kunde, 'highlight' => $eintrag->subject_id])
                    : null,
            ]);
    }

    /**
     * Was bei den Agenten zu tun ist: Rechner, die nicht mehr melden,
     * fehlgeschlagene Durchlaeufe, fehlgeschlagene oder warnende Backups,
     * Token, die bald ablaufen.
     *
     * null, wenn der Kunde weder Agenten noch Token hat - dann gibt es gar
     * keine Kachel.
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
        // Backups, deren letzter Durchlauf fehlschlug oder warnte, wie ein
        // Agent es gemeldet hat.
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
                'text' => $eines->istAbgelaufen()
                    ? __('abgelaufen')
                    : __('läuft ab in :tage Tagen', ['tage' => (int) now()->startOfDay()->diffInDays($eines->expires_at->copy()->startOfDay())]),
                'schwer' => $eines->istAbgelaufen(),
            ]);
        }

        return $warnungen;
    }

    /**
     * Die Geraetearten mit Beschaffungsdaten, die der Nutzer sehen darf.
     *
     * Garantie und Support-Ende stehen beide in HatBeschaffung und fragen
     * dieselbe Liste ab.
     *
     * @return Collection<string, array{0: class-string<Model>, 1: string}>
     */
    private function beschaffungsarten(): Collection
    {
        return collect(config('custom.trashables'))
            ->filter(fn ($eintrag, $slug) => in_array(HatBeschaffung::class, class_uses_recursive($eintrag[0]), true)
                && $this->darf($slug));
    }

    private function darf(string $slug): bool
    {
        return Gate::forUser($this->nutzer)->allows($slug.'_viewAny');
    }

    /**
     * Eine Zeile einer Fristen-Kachel - fuer alle vier dieselbe Form.
     *
     * "tage" ist das Vorzeichen, an dem Browser und App faerben: negativ
     * abgelaufen, 0 heute. Wer sie schon gerechnet hat (garantieTage()),
     * gibt sie mit.
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
            // ?highlight= wie aus der globalen Suche: die Liste springt zum
            // Eintrag und markiert ihn.
            'url' => Route::has($slug.'.index')
                ? route($slug.'.index', [$this->kunde, 'highlight' => $id])
                : null,
        ];
    }
}
