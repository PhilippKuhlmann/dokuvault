<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Der Ablauf des Erstaufnahme-Assistenten: welche Schritte ein Nutzer sieht
 * und wie ein Durchlauf von einem zum naechsten kommt.
 *
 * Einmal geschrieben, zweimal gerufen - von der Livewire-Komponente im
 * Browser und vom Mobile\WizardController. Das Ausfuellen selbst gehoert
 * nicht hierher: Im Browser macht es die Komponente, in der App das
 * Formular, das der Server ohnehin schon beschreibt
 * (`customers/{slug}/forms/{typ}`).
 */
class Erstaufnahme
{
    public function __construct(
        private Customer $kunde,
        private User $nutzer,
    ) {}

    /**
     * Nur Schritte, fuer die der Nutzer das jeweilige _create-Recht hat.
     *
     * Hat er fuer keinen ein Recht, ist der Assistent fuer ihn nicht
     * nutzbar - dann ist die Liste leer, und der Zugang ist 403.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schritte(): array
    {
        return collect(config('custom.wizard_steps'))
            ->filter(fn (array $schritt) => Gate::forUser($this->nutzer)->allows($schritt['permission']))
            ->map(fn (array $schritt) => $this->fernwartungBeschriften($schritt))
            ->values()
            ->all();
    }

    /**
     * Die beiden Fernwartungsfelder heissen nach dem eingestellten Werkzeug.
     *
     * Die Ersetzung passiert hier und nicht in der Konfiguration: "php
     * artisan config:cache" friert config/custom.php ein, ein Wert aus der
     * Datenbank waere darin fuer immer eingebacken und liesse sich ueber die
     * Einstellungen nicht mehr aendern.
     *
     * @param  array<string, mixed>  $schritt
     * @return array<string, mixed>
     */
    public function fernwartungBeschriften(array $schritt): array
    {
        $werkzeug = Setting::fernwartung();

        $schritt['fields'] = collect($schritt['fields'] ?? [])->map(function (array $feld) use ($werkzeug) {
            $feld['label'] = match ($feld['name'] ?? null) {
                'remoteID' => $werkzeug['id_label'],
                'remotePassword' => $werkzeug['password_label'],
                default => $feld['label'] ?? '',
            };

            return $feld;
        })->all();

        return $schritt;
    }

    /** Der offene Durchlauf dieses Nutzers - oder keiner. */
    public function durchlauf(): ?DocumentationRun
    {
        return DocumentationRun::where('customer_id', $this->kunde->id)
            ->where('user_id', $this->nutzer->id)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();
    }

    /** Den offenen Durchlauf holen oder einen neuen beginnen. */
    public function beginnen(): DocumentationRun
    {
        return $this->durchlauf() ?? DocumentationRun::create([
            'customer_id' => $this->kunde->id,
            'user_id' => $this->nutzer->id,
            'current_step' => $this->schritte()[0]['key'] ?? null,
            'completed_steps' => [],
            'skipped_steps' => [],
        ]);
    }

    /** @return array<string, mixed>|null */
    public function aktueller(DocumentationRun $durchlauf): ?array
    {
        if ($durchlauf->current_step === null) {
            return null;
        }

        return collect($this->schritte())->firstWhere('key', $durchlauf->current_step);
    }

    /** Ob dieser Schluessel ein Schritt ist, den der Nutzer sehen darf. */
    public function erlaubt(string $schluessel): bool
    {
        return collect($this->schritte())->contains(fn ($schritt) => $schritt['key'] === $schluessel);
    }

    /**
     * Einen Schritt verlassen, ohne "Weiter" zu waehlen.
     *
     * Wurde in diesem Durchlauf darin etwas angelegt, zaehlt er trotzdem als
     * erfasst - nur ein wirklich leer gelassener Schritt gilt als
     * uebersprungen. So steht am Ende nicht "uebersprungen" an einem
     * Schritt, in dem man gerade etwas eingetragen hat.
     */
    public function verlassen(DocumentationRun $durchlauf, string $schluessel): void
    {
        if (! empty($durchlauf->created_records[$schluessel] ?? [])) {
            $durchlauf->markStepCompleted($schluessel);
        } else {
            $durchlauf->markStepSkipped($schluessel);
        }
    }

    /**
     * Zum naechsten Schritt. Gibt es keinen mehr, ist der Durchlauf fertig.
     */
    public function weiter(DocumentationRun $durchlauf): void
    {
        $schluessel = array_column($this->schritte(), 'key');
        $stelle = array_search($durchlauf->current_step, $schluessel, true);
        $naechster = ($stelle !== false && isset($schluessel[$stelle + 1])) ? $schluessel[$stelle + 1] : null;

        $durchlauf->update(['current_step' => $naechster]);

        if ($naechster === null) {
            $durchlauf->update(['completed_at' => now()]);
        }
    }

    /** Einen Schritt zurueck. Am Anfang bleibt es, wo es ist. */
    public function zurueck(DocumentationRun $durchlauf): void
    {
        $schluessel = array_column($this->schritte(), 'key');
        $stelle = array_search($durchlauf->current_step, $schluessel, true);

        if ($stelle !== false && $stelle > 0) {
            $durchlauf->update(['current_step' => $schluessel[$stelle - 1]]);
        }
    }

    /**
     * Direkt zu einem Schritt springen - vor oder zurueck.
     *
     * Nur zu erlaubten: Ein unbekannter oder gesperrter Schluessel wird
     * still verworfen, statt den Durchlauf in einen Zustand zu bringen, den
     * der Nutzer gar nicht sehen darf. Springen ist kein Bearbeiten - der
     * verlassene Schritt wird weder erledigt noch uebersprungen.
     */
    public function springen(DocumentationRun $durchlauf, string $schluessel): bool
    {
        if (! $this->erlaubt($schluessel)) {
            return false;
        }

        $durchlauf->update(['current_step' => $schluessel]);

        return true;
    }

    /**
     * Die ganze Gruppe des aktuellen Schritts ueberspringen ("keine
     * Telefonie"): alle noch offenen Schritte dieser Gruppe vermerken und
     * zum ersten Schritt danach springen.
     */
    public function gruppeUeberspringen(DocumentationRun $durchlauf): void
    {
        $schritte = $this->schritte();
        $aktuell = $this->aktueller($durchlauf);

        if (! $aktuell) {
            return;
        }

        $schluessel = array_column($schritte, 'key');
        $stelle = array_search($durchlauf->current_step, $schluessel, true);

        if ($stelle === false) {
            return;
        }

        $naechster = null;

        foreach (array_slice($schritte, $stelle) as $schritt) {
            if ($schritt['group'] !== $aktuell['group']) {
                $naechster = $schritt['key'];
                break;
            }

            $this->verlassen($durchlauf, $schritt['key']);
        }

        $durchlauf->update(['current_step' => $naechster]);

        if ($naechster === null) {
            $durchlauf->update(['completed_at' => now()]);
        }
    }

    /**
     * "Als erledigt markieren": schliesst einen offenen Durchlauf ab. Gibt
     * es keinen, entsteht ein bereits abgeschlossener - das Dashboard fragt
     * nur, ob es fuer den Kunden einen abgeschlossenen gibt.
     */
    public function abschliessen(): void
    {
        $geschlossen = DocumentationRun::where('customer_id', $this->kunde->id)
            ->where('user_id', $this->nutzer->id)
            ->whereNull('completed_at')
            ->update(['current_step' => null, 'completed_at' => now()]);

        if ($geschlossen === 0) {
            DocumentationRun::create([
                'customer_id' => $this->kunde->id,
                'user_id' => $this->nutzer->id,
                'completed_steps' => [],
                'skipped_steps' => [],
                'completed_at' => now(),
            ]);
        }
    }
}
