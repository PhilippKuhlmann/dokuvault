<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The flow of the initial-survey wizard: which steps a user sees and how a
 * run gets from one to the next.
 *
 * Written once, called twice - by the Livewire component in the browser and
 * by the Mobile\WizardController. Filling things in does not belong here:
 * in the browser the component does it, in the app the form that the server
 * already describes anyway
 * (`customers/{slug}/forms/{typ}`).
 */
class WizardFlow
{
    public function __construct(
        private Customer $kunde,
        private User $nutzer,
    ) {}

    /**
     * Only steps the user holds the matching _create right for.
     *
     * If they hold none, the wizard is unusable for them - then the list is
     * empty and access is 403.
     *
     * @return array<int, array<string, mixed>>
     */
    public function steps(): array
    {
        return collect(config('custom.wizard_steps'))
            ->filter(fn (array $schritt) => Gate::forUser($this->nutzer)->allows($schritt['permission']))
            ->map(fn (array $schritt) => $this->labelRemoteAccess($schritt))
            ->values()
            ->all();
    }

    /**
     * The two remote-access fields are named after the configured tool.
     *
     * The substitution happens here and not in the configuration: "php artisan
     * config:cache" freezes config/custom.php, a value from the database would
     * be baked into it for good and could no longer be changed through the
     * settings.
     *
     * @param  array<string, mixed>  $schritt
     * @return array<string, mixed>
     */
    public function labelRemoteAccess(array $schritt): array
    {
        $werkzeug = Setting::remoteAccess();

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

    /** This user's open run - or none. */
    public function durchlauf(): ?DocumentationRun
    {
        return DocumentationRun::where('customer_id', $this->kunde->id)
            ->where('user_id', $this->nutzer->id)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();
    }

    /** Fetch the open run or begin a new one. */
    public function beginnen(): DocumentationRun
    {
        return $this->durchlauf() ?? DocumentationRun::create([
            'customer_id' => $this->kunde->id,
            'user_id' => $this->nutzer->id,
            'current_step' => $this->steps()[0]['key'] ?? null,
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

        return collect($this->steps())->firstWhere('key', $durchlauf->current_step);
    }

    /** Whether this key is a step the user may see. */
    public function erlaubt(string $schluessel): bool
    {
        return collect($this->steps())->contains(fn ($schritt) => $schritt['key'] === $schluessel);
    }

    /**
     * Leave a step without choosing "next".
     *
     * If something was created in it during this run it still counts as
     * captured - only a step genuinely left empty counts as skipped. That way
     * "skipped" does not end up on a step where something was just entered.
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
     * On to the next step. If there is none left, the run is finished.
     */
    public function weiter(DocumentationRun $durchlauf): void
    {
        $schluessel = array_column($this->steps(), 'key');
        $stelle = array_search($durchlauf->current_step, $schluessel, true);
        $naechster = ($stelle !== false && isset($schluessel[$stelle + 1])) ? $schluessel[$stelle + 1] : null;

        $durchlauf->update(['current_step' => $naechster]);

        if ($naechster === null) {
            $durchlauf->update(['completed_at' => now()]);
        }
    }

    /** One step back. At the start it stays where it is. */
    public function zurueck(DocumentationRun $durchlauf): void
    {
        $schluessel = array_column($this->steps(), 'key');
        $stelle = array_search($durchlauf->current_step, $schluessel, true);

        if ($stelle !== false && $stelle > 0) {
            $durchlauf->update(['current_step' => $schluessel[$stelle - 1]]);
        }
    }

    /**
     * Jump straight to a step - forwards or back.
     *
     * Only to allowed ones: an unknown or blocked key is dropped silently
     * instead of putting the run into a state the user may not see at all.
     * Jumping is not editing - the step left behind is neither done nor
     * skipped.
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
     * Skip the whole group of the current step ("no
     * telephony"): note every step of that group still open and jump to the
     * first step after it.
     */
    public function skipGroup(DocumentationRun $durchlauf): void
    {
        $schritte = $this->steps();
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
     * "Mark as done": closes an open run. If there is none, one that is
     * already closed comes into being - the dashboard only asks whether there
     * is a closed one for the customer.
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
