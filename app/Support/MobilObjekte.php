<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Wie die App ein dokumentiertes Objekt zu sehen bekommt.
 *
 * Die Felder kommen aus config/forms.php - derselben Beschreibung, aus der das
 * Modal im Browser sein Formular baut. Eine eigene Liste fuer die App waere
 * nach dem naechsten neuen Feld veraltet, ohne dass es jemand merkt.
 *
 * Geheimnisse gehen hier nie hinaus, nur die Tatsache, dass eines gesetzt
 * ist. Den Wert gibt es einzeln ueber App\Support\Kennwortabruf - mit Bremse
 * und Protokolleintrag, wie im Browser.
 */
class MobilObjekte
{
    /**
     * Feldarten, die die App nicht zeigt: Dateien und Pfade sind dort nicht zu
     * oeffnen, die Verknuepfungen und Dienste brauchen eigene Ansichten.
     */
    private const OHNE = ['versteckt', 'datei', 'host', 'dienste', 'verknuepfung'];

    /** Wie viele Eintraege eine Liste in der App hoechstens bekommt. */
    public const LISTE_HOECHSTENS = 500;

    /**
     * Alle Typen, die die App kennt: Slug => [Model, Recht, Felder|null].
     *
     * Die Formulartypen plus das, was die globale Suche findet, aber kein
     * Modal hat (Serverschrank, Patchfeld, Netzwerkdose) - ein Treffer, den
     * man nicht oeffnen kann, ist keiner.
     *
     * @return array<string, array{0: class-string<Model>, 1: string, 2: array|null}>
     */
    public function typen(): array
    {
        $typen = [];

        foreach (config('forms') as $slug => $formular) {
            $typen[$slug] = [$formular['model'], $slug, $formular['felder']];
        }

        foreach (GlobaleSuche::TYPES as $slug => $eintrag) {
            $typen[$slug] ??= [$eintrag[0], $eintrag[2], null];
        }

        return $typen;
    }

    /** Die Typen, deren Liste dieser Benutzer sehen darf. */
    public function sichtbareTypen(User $nutzer): array
    {
        return array_filter(
            $this->typen(),
            fn ($typ) => Gate::forUser($nutzer)->allows($typ[1].'_viewAny'),
        );
    }

    /** Mehrzahl fuer Ueberschriften, wie in der Seitenleiste. */
    public function titel(string $slug): string
    {
        return __(config('custom.list_titles')[$slug]
            ?? config('custom.trashables')[$slug][1]
            ?? GlobaleSuche::TYPES[$slug][1]
            ?? $slug);
    }

    /** Einzahl: "Server", "Zertifikat". */
    public function einzahl(string $slug): string
    {
        return __(config('forms')[$slug]['einzahl']
            ?? config('custom.trashables')[$slug][1]
            ?? GlobaleSuche::TYPES[$slug][1]
            ?? $slug);
    }

    /**
     * Ein Objekt holen - mit Recht und Kundenzugehoerigkeit.
     *
     * 404 fuer einen unbekannten Typ und fuer ein fremdes Objekt eines
     * Kundennutzers: Er soll nicht einmal erfahren, dass es die Nummer gibt.
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

    /** Eine Zeile in Liste und Suche. */
    public function kurz(Model $objekt, string $slug): array
    {
        return [
            'typ' => $slug,
            'id' => $objekt->getKey(),
            'name' => $this->name($objekt, $slug),
            'untertitel' => $this->untertitel($objekt),
            'kunde' => $this->kunde($objekt->customer),
        ];
    }

    /** Alles, was die Detailansicht zeigt. */
    public function detail(Model $objekt, string $slug, User $nutzer): array
    {
        $felder = $this->typen()[$slug][2];

        return [
            ...$this->kurz($objekt, $slug),
            'einzahl' => $this->einzahl($slug),
            'gruppen' => $felder !== null
                ? $this->gruppen($objekt, $felder)
                : $this->gruppenOhneFormular($objekt),
            'ip_adressen' => method_exists($objekt, 'ipAddresses')
                ? $objekt->ipAddresses()->with('network')->get()->map(fn ($ip) => [
                    'adresse' => $ip->anzeige(),
                    'bezeichnung' => $ip->label,
                    'netz' => $ip->network?->protokollName(),
                ])->values()->all()
                : [],
            'zugangsdaten' => $this->zugangsdaten($objekt, $nutzer),
        ];
    }

    public function kunde(?Customer $kunde): ?array
    {
        return $kunde ? ['slug' => $kunde->slug, 'name' => $kunde->name] : null;
    }

    private function name(Model $objekt, string $slug): string
    {
        $name = method_exists($objekt, 'protokollName') ? $objekt->protokollName() : null;

        return $name ?: $this->einzahl($slug).' #'.$objekt->getKey();
    }

    /** Woran man das Objekt in einer Liste wiedererkennt, ohne es zu oeffnen. */
    private function untertitel(Model $objekt): ?string
    {
        $teile = [
            $objekt->site_id ? $objekt->site?->name : null,
            $objekt->serialNumber,
        ];

        return collect($teile)->filter()->implode(' · ') ?: null;
    }

    /** Die Felder aus dem Formular, nach dessen Abschnitten gruppiert. */
    private function gruppen(Model $objekt, array $felder): array
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

        return collect($gruppen)
            ->map(fn ($eintraege, $titel) => ['titel' => $titel, 'felder' => $eintraege])
            ->values()
            ->all();
    }

    /**
     * Ein Feld - oder null, wenn es leer ist.
     *
     * Leere Felder bleiben weg: Auf dem Telefon ist eine Seite voller
     * Striche nur Scrollweg.
     */
    private function feld(Model $objekt, array $feld): ?array
    {
        $name = $feld['name'];
        $label = __($feld['label']);

        if ($this->istGeheim($name)) {
            // Den Rohwert pruefen, nicht den entschluesselten: Die Frage ist
            // nur, ob etwas drinsteht.
            return filled($objekt->getRawOriginal($name))
                ? ['name' => $name, 'label' => $label, 'art' => 'geheim', 'wert' => null]
                : null;
        }

        $roh = $objekt->getAttribute($name);

        if ($roh === null || $roh === '') {
            return null;
        }

        [$art, $wert] = match ($feld['type']) {
            'date' => ['datum', $roh instanceof Carbon ? $roh->toDateString() : (string) $roh],
            'schalter' => ['text', $roh ? __('Ja') : __('Nein')],
            'einheit' => ['text', $roh.' '.($feld['einheit'] ?? '')],
            'mehrzeilig' => ['mehrzeilig', (string) $roh],
            'optionen' => ['text', __((string) ($this->optionen($feld)[$roh] ?? $roh))],
            'standort' => ['text', $objekt->site?->name],
            'auswahl' => ['text', $feld['quelle']::find($roh)?->{$feld['anzeige'] ?? 'name'}],
            default => [$this->artVonText($roh), (string) $roh],
        };

        if ($wert === null || $wert === '') {
            return null;
        }

        return ['name' => $name, 'label' => $label, 'art' => $art, 'wert' => $wert];
    }

    /**
     * Dieselbe Regel wie im Modal (objekt-formular.blade.php): Ein Standserver
     * hat keine Einbautiefe, auch wenn in der Spalte noch eine steht.
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

    /** Fuer Objekte ohne Formular (Schrank, Patchfeld, Dose): die Spalten selbst. */
    private function gruppenOhneFormular(Model $objekt): array
    {
        $versteckt = ['id', 'customer_id', 'site_id', 'created_at', 'updated_at', 'deleted_at'];

        $felder = collect($objekt->attributesToArray())
            ->except($versteckt)
            ->reject(fn ($wert, $name) => $this->istGeheim($name) || $wert === null || $wert === '' || is_array($wert))
            ->map(fn ($wert, $name) => [
                'name' => $name,
                'label' => $name,
                'art' => $this->artVonText($wert),
                'wert' => is_bool($wert) ? ($wert ? __('Ja') : __('Nein')) : (string) $wert,
            ])
            ->values()
            ->all();

        return $felder ? [['titel' => __('Allgemein'), 'felder' => $felder]] : [];
    }

    /** Die verknuepften Zugaenge, ohne Kennwort - das holt die App einzeln. */
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
                'art' => $link->login->istSchluessel() ? 'schluessel' : 'kennwort',
                'notiz' => $link->note,
                'hat_kennwort' => filled($link->login->getRawOriginal('password')),
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

    /** Damit die App eine Adresse antippbar machen kann. */
    private function artVonText($wert): string
    {
        $wert = (string) $wert;

        return match (true) {
            str_starts_with($wert, 'http://') || str_starts_with($wert, 'https://') => 'link',
            filter_var($wert, FILTER_VALIDATE_IP) !== false => 'ip',
            default => 'text',
        };
    }
}
