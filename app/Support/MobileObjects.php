<?php

namespace App\Support;

use App\Models\ADDomainHost;
use App\Models\Customer;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Models\Service;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * How the app gets to see a documented object.
 *
 * The fields come from config/forms.php - the same description the modal in
 * the browser builds its form from. A list of its own for the app would be
 * out of date after the next new field, with nobody noticing.
 *
 * Secrets never leave through here, only the fact that one is set. The value
 * is available one at a time through App\Support\PasswordReveal - with a
 * throttle and a log entry, as in the browser.
 */
class MobileObjects
{
    /**
     * Field kinds the app does not show: files cannot be opened there, and
     * hidden fields are not visible in the browser either.
     */
    private const OHNE = ['versteckt', 'datei'];

    /** How many entries a list in the app gets at most. */
    public const LISTE_HOECHSTENS = 500;

    /**
     * Every kind the app knows: slug => [model, permission, fields|null].
     *
     * The form kinds plus whatever the global search finds but has no modal
     * for (rack, patch panel, network outlet) - a hit you cannot open is
     * no hit.
     *
     * @return array<string, array{0: class-string<Model>, 1: string, 2: array|null}>
     */
    public function typen(): array
    {
        $typen = [];

        foreach (config('forms') as $slug => $formular) {
            $typen[$slug] = [$formular['model'], $slug, $formular['felder']];
        }

        // Not the types with a page of their own (checklists): the app has no
        // view for them yet and would list entries it cannot open.
        foreach (array_diff_key(Search::TYPES, array_flip(Search::ZUR_DETAILSEITE)) as $slug => $eintrag) {
            $typen[$slug] ??= [$eintrag[0], $eintrag[2], null];
        }

        return $typen;
    }

    /** The kinds whose list this user may see. */
    public function visibleKinds(User $nutzer): array
    {
        return array_filter(
            $this->typen(),
            fn ($typ) => Gate::forUser($nutzer)->allows($typ[1].'_viewAny'),
        );
    }

    /**
     * Whether the app may offer "new" for a type: the right to create, and a
     * way to do it - a form type (ObjectController), the rack or the patch
     * panel. A network outlet comes with its panel; a button there would
     * lead nowhere.
     */
    public function darfAnlegen(User $nutzer, string $slug): bool
    {
        $typ = $this->typen()[$slug] ?? null;

        if ($typ === null || ($typ[2] === null && ! in_array($slug, ['rack', 'patchpanel'], true))) {
            return false;
        }

        return Gate::forUser($nutzer)->allows($typ[1].'_create');
    }

    /** Plural for headings, as in the sidebar. */
    public function titel(string $slug): string
    {
        return __(config('custom.list_titles')[$slug]
            ?? config('custom.trashables')[$slug][1]
            ?? Search::TYPES[$slug][1]
            ?? $slug);
    }

    /** Einzahl: "Server", "Zertifikat". */
    public function singular(string $slug): string
    {
        return __(config('forms')[$slug]['einzahl']
            ?? config('custom.trashables')[$slug][1]
            ?? Search::TYPES[$slug][1]
            ?? $slug);
    }

    /**
     * Fetch an object - with permission and customer check.
     *
     * 404 for an unknown kind and for a foreign object of a customer user:
     * they should not even learn that the number exists.
     */
    public function finden(string $slug, int $id, User $nutzer): Model
    {
        $typ = $this->typen()[$slug] ?? abort(404);

        Gate::forUser($nutzer)->authorize($typ[1].'_viewAny');

        $objekt = $typ[0]::query()
            ->when($nutzer->hasCustomer(), fn ($abfrage) => $abfrage->where('customer_id', $nutzer->customer_id))
            ->find($id);

        return $objekt ?? abort(404);
    }

    /** One row in list and search. */
    public function kurz(Model $objekt, string $slug): array
    {
        return [
            'typ' => $slug,
            'id' => $objekt->getKey(),
            'name' => $this->name($objekt, $slug),
            'subtitle' => $this->subtitle($objekt),
            'customer' => $this->kunde($objekt->customer),
        ];
    }

    /** Everything the detail view shows. */
    public function detail(Model $objekt, string $slug, User $nutzer): array
    {
        $felder = $this->typen()[$slug][2];

        return [
            ...$this->kurz($objekt, $slug),
            'singular' => $this->singular($slug),
            'groups' => $felder !== null
                ? $this->groups($objekt, $felder)
                : $this->groupsWithoutForm($objekt),
            'ip_addresses' => method_exists($objekt, 'ipAddresses')
                ? $objekt->ipAddresses()->with('network')->get()->map(fn ($ip) => [
                    // id and dhcp to remove it again (DeviceController).
                    'id' => $ip->id,
                    'dhcp' => (bool) $ip->dhcp,
                    'address' => $ip->label(),
                    'label' => $ip->label,
                    'network' => $ip->network?->activityName(),
                ])->values()->all()
                : [],
            'credentials' => $this->zugangsdaten($objekt, $nutzer),
            // The key stays German: it is part of the response the iOS app
            // reads - changing it belongs together with the app.
            'mounting' => $this->mountLocation($objekt),
            'end_of_support' => $this->supportEnde($objekt),
            'scan_targets' => $objekt instanceof Scanner
                ? $objekt->scanTargets()->with('login')->get()->map(fn ($ziel) => $this->scanZiel($ziel))->values()->all()
                : [],
        ];
    }

    /** A scan target of a scanner - where it scans to, and with which login. */
    public function scanZiel(ScanTarget $ziel): array
    {
        return [
            'id' => $ziel->id,
            'name' => $ziel->name,
            'kind' => $ziel->kind,
            'kind_label' => __(ScanTarget::ARTEN[$ziel->kind] ?? ScanTarget::ARTEN['other']),
            'target' => $ziel->target,
            'login' => $ziel->login ? ['id' => $ziel->login->id, 'name' => $ziel->login->name] : null,
        ];
    }

    /** Where the device is fitted - the way back from the rack view. */
    private function mountLocation(Model $objekt): ?array
    {
        if (! method_exists($objekt, 'mountLocation') || ! ($text = $objekt->mountLocation())) {
            return null;
        }

        $rack = $objekt->rackItem->rack;

        return ['text' => $text, 'rack_id' => $rack->id, 'customer' => $this->kunde($rack->customer)];
    }

    /**
     * Support end of the operating system with its state, as the badge in
     * the browser (x-eol): "abgelaufen", "bald" (within the period from the
     * settings) or "ok". Null without a maintained date.
     */
    private function supportEnde(Model $objekt): ?array
    {
        $os = method_exists($objekt, 'operatingSystem') ? $objekt->operatingSystem : null;

        if (! $os?->eol_date) {
            return null;
        }

        return [
            'operating_system' => $os->name,
            'date' => $os->eol_date->toDateString(),
            'state' => match (true) {
                $os->istEol() => 'abgelaufen',
                $os->laeuftAus() => 'bald',
                default => 'ok',
            },
        ];
    }

    public function kunde(?Customer $kunde): ?array
    {
        return $kunde ? ['slug' => $kunde->slug, 'name' => $kunde->name] : null;
    }

    private function name(Model $objekt, string $slug): string
    {
        $name = method_exists($objekt, 'activityName') ? $objekt->activityName() : null;

        return $name ?: $this->singular($slug).' #'.$objekt->getKey();
    }

    /** What makes the object recognisable in a list, without opening it. */
    private function subtitle(Model $objekt): ?string
    {
        $teile = [
            $objekt->site_id ? $objekt->site?->name : null,
            $objekt->serialNumber,
        ];

        return collect($teile)->filter()->implode(' · ') ?: null;
    }

    /** The fields from the form, grouped by its sections. */
    private function groups(Model $objekt, array $felder): array
    {
        $gruppen = [];

        foreach ($felder as $feld) {
            if (in_array($feld['type'], self::OHNE, true) || ! $this->sichtbar($objekt, $feld)) {
                continue;
            }

            $eintrag = $this->feld($objekt, $feld);

            if ($eintrag === null) {
                continue;
            }

            $gruppen[__($feld['gruppe'] ?? 'Allgemein')][] = $eintrag;
        }

        // Derived from the public key and therefore no form field - but the
        // value one compares on the server (x-fingerprint in the browser).
        if ($objekt instanceof SshKey && filled($objekt->fingerprint)) {
            $gruppen[__('Allgemein')][] = ['name' => 'fingerprint', 'label' => __('Fingerprint'), 'kind' => 'text', 'value' => $objekt->fingerprint];
        }

        return collect($gruppen)
            ->map(fn ($eintraege, $titel) => ['title' => $titel, 'fields' => $eintraege])
            ->values()
            ->all();
    }

    /**
     * One field - or null when it is empty.
     *
     * Empty fields are left out: on a phone a page full of dashes is just
     * scrolling distance.
     */
    private function feld(Model $objekt, array $feld): ?array
    {
        $name = $feld['name'];
        $label = __($feld['label']);

        // No column behind these three. They go out as plain text, so the app
        // shows them without knowing them; "verweise" and "liste" carry the
        // parts for a jump to the device or a folded list.
        if ($feld['type'] === 'host') {
            $verweise = $this->hostVerweise($objekt, $feld['rolle']);

            return $verweise ? [
                'name' => $name, 'label' => $label, 'kind' => 'text',
                'value' => collect($verweise)->pluck('name')->implode(', '),
                'links' => $verweise,
            ] : null;
        }

        if ($feld['type'] === 'verknuepfung') {
            $namen = $objekt->{$feld['relation']}
                ->pluck($feld['anzeige'] ?? 'name')
                ->filter()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            return $namen->isNotEmpty() ? [
                'name' => $name, 'label' => $label, 'kind' => 'text',
                'value' => $namen->implode(', '),
                'list' => $namen->all(),
            ] : null;
        }

        if ($feld['type'] === 'dienste') {
            $dienste = array_values(array_filter((array) $objekt->getAttribute($name), 'filled'));

            // The colours as in the browser: from the service catalogue, text
            // colour computed here (Service::kachelFarben). Without a colour
            // in the catalogue both are null - the app shows it grey.
            $katalog = Service::farbzuordnung();

            return $dienste ? [
                'name' => $name, 'label' => $label, 'kind' => 'text',
                'value' => implode(' · ', $dienste),
                'list' => $dienste,
                'tiles' => array_map(fn ($dienst) => ['name' => $dienst]
                    + (Service::kachelFarben($katalog[mb_strtolower(trim($dienst))] ?? null) ?? ['color' => null, 'text_color' => null]),
                    $dienste),
            ] : null;
        }

        if ($this->istGeheim($name)) {
            // Check the raw value, not the decrypted one: the question is
            // only whether something is in there.
            return filled($objekt->getRawOriginal($name))
                ? ['name' => $name, 'label' => $label, 'kind' => 'secret', 'value' => null]
                : null;
        }

        $roh = $objekt->getAttribute($name);

        if ($roh === null || $roh === '') {
            return null;
        }

        [$art, $wert] = match ($feld['type']) {
            'date' => ['date', $roh instanceof Carbon ? $roh->toDateString() : (string) $roh],
            'schalter' => ['text', $roh ? __('Ja') : __('Nein')],
            'einheit' => ['text', $roh.' '.($feld['einheit'] ?? '')],
            'mehrzeilig' => ['multiline', (string) $roh],
            'optionen' => ['text', __((string) ($this->optionen($feld)[$roh] ?? $roh))],
            'standort' => ['text', $objekt->site?->name],
            'auswahl' => ['text', $feld['quelle']::find($roh)?->{$feld['anzeige'] ?? 'name'}],
            default => [$this->typeFromText($roh), (string) $roh],
        };

        if ($wert === null || $wert === '') {
            return null;
        }

        return ['name' => $name, 'label' => $label, 'kind' => $art, 'value' => $wert];
    }

    /**
     * The same rule as in the modal (object-modal.blade.php): a tower server
     * has no mounting depth, even if the column still holds one.
     * Mehrere Bedingungen gelten zusammen.
     */
    private function sichtbar(Model $objekt, array $feld): bool
    {
        foreach ($feld['sichtbar_wenn'] ?? [] as $anderes => $erwartet) {
            $wert = (string) $objekt->getRawOriginal($anderes);

            $erfuellt = match (true) {
                is_array($erwartet) && isset($erwartet['enthaelt']) => str_contains(strtolower($wert), strtolower($erwartet['enthaelt'])),
                is_array($erwartet) && isset($erwartet['leer']) => $wert === '',
                default => $wert === (string) $erwartet,
            };

            if (! $erfuellt) {
                return false;
            }
        }

        return true;
    }

    /**
     * The machines behind a 'host' field, to open with /objects/{typ}/{id}.
     * The form keys ("server:5") carry the type already - the same slugs as
     * the app's types. A machine in the trash drops out, as in the browser.
     */
    private function hostVerweise(Model $objekt, string $rolle): array
    {
        return collect($objekt->hostKeys($rolle))
            ->map(function (string $schluessel) {
                [$typ, $id] = array_pad(explode(':', $schluessel, 2), 2, null);
                $klasse = ADDomainHost::TYPES[$typ] ?? null;
                $geraet = $klasse ? $klasse::find($id) : null;

                return $geraet ? ['typ' => $typ, 'id' => $geraet->getKey(), 'name' => $geraet->name] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /** For objects without a form (rack, patch panel, outlet): the columns themselves. */
    private function groupsWithoutForm(Model $objekt): array
    {
        $versteckt = ['id', 'customer_id', 'site_id', 'created_at', 'updated_at', 'deleted_at'];

        $felder = collect($objekt->attributesToArray())
            ->except($versteckt)
            ->reject(fn ($wert, $name) => $this->istGeheim($name) || $wert === null || $wert === '' || is_array($wert))
            ->map(fn ($wert, $name) => [
                'name' => $name,
                'label' => $name,
                'kind' => $this->typeFromText($wert),
                'value' => is_bool($wert) ? ($wert ? __('Ja') : __('Nein')) : (string) $wert,
            ])
            ->values()
            ->all();

        return $felder ? [['title' => __('Allgemein'), 'fields' => $felder]] : [];
    }

    /** The linked credentials, without the password - the app fetches that one at a time. */
    private function zugangsdaten(Model $objekt, User $nutzer): array
    {
        if (! method_exists($objekt, 'zugangsdaten')) {
            return [];
        }

        $sichtbar = collect([
            'password' => 'logingeneral_viewAny',
            'sshkey' => 'sshkey_viewAny',
        ])->filter(fn ($recht) => Gate::forUser($nutzer)->allows($recht))->keys()->all();

        return $objekt->zugangsdaten()
            ->filter(fn ($link) => in_array($link->login->kind, $sichtbar, true))
            ->map(fn ($link) => [
                'id' => $link->id,
                'name' => $link->login->name,
                'benutzer' => $link->login->username,
                'kind' => $link->login->istSchluessel() ? 'ssh_key' : 'password',
                'notiz' => $link->note,
                'hat_kennwort' => filled($link->login->getRawOriginal('password')),
                // The value one compares on the server; null for a password.
                'fingerprint' => $link->login->istSchluessel() ? $link->login->fingerprint : null,
            ])
            ->values()
            ->all();
    }

    private function istGeheim(string $feld): bool
    {
        return in_array($feld, config('custom.secret_columns'), true);
    }

    private function optionen(array $feld): array
    {
        return $feld['werte'] ?? (isset($feld['quelle']) ? config($feld['quelle'], []) : []);
    }

    /** So the app can make an address tappable. */
    private function typeFromText($wert): string
    {
        $wert = (string) $wert;

        return match (true) {
            str_starts_with($wert, 'http://') || str_starts_with($wert, 'https://') => 'link',
            filter_var($wert, FILTER_VALIDATE_IP) !== false => 'ip',
            default => 'text',
        };
    }
}
