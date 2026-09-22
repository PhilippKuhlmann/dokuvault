<?php

namespace App\Livewire;

use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * API-Token anlegen und widerrufen.
 *
 * Vorher war /admin/apitoken eine Route, die rohes JSON zurueckgab - und bei
 * jedem Aufruf einen weiteren Token namens "optin" anlegte. Ein Menuepunkt
 * darauf haette bei jedem Klick einen Token erzeugt, ohne dass jemand die
 * alten je wieder losgeworden waere.
 *
 * Gezeigt werden die Token des angemeldeten Benutzers: Ein Token spricht mit
 * seinen Rechten, fremde Token zu verwalten waere eine andere Befugnis.
 */
class AdminApiToken extends Component
{
    use PrueftWaehrendDerEingabe;

    /** Name des neuen Tokens - wofuer er da ist. */
    public string $name = '';

    /**
     * Ablaufdatum des neuen Tokens - Pflicht.
     *
     * Ein Token ohne Frist ist ein Dauerzugang mit den Rechten seines
     * Benutzers, der im Skript liegt, das ihn nutzt. Vorgefuellt aus der
     * Konfiguration, aenderbar.
     */
    public string $expiresAt = '';

    /**
     * Der Klartext, genau einmal.
     *
     * Gespeichert wird nur der Hash; wer ihn jetzt nicht mitnimmt, muss einen
     * neuen anlegen. Deshalb steht er gross auf der Seite und nicht in einer
     * Meldung, die nach drei Sekunden verschwindet.
     */
    public ?string $frischerToken = null;

    /** Eine Quelle fuer das Anlegen und fuer die Pruefung waehrend der Eingabe. */
    protected function regeln(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Pflicht und in der Zukunft: kein unbegrenzter Token.
            'expiresAt' => ['required', 'date', 'after:today'],
        ];
    }

    protected function feldnamen(): array
    {
        return ['name' => __('Bezeichnung'), 'expiresAt' => __('Läuft ab am')];
    }

    public function mount(): void
    {
        Gate::authorize('admin_apitoken');

        $this->expiresAt = $this->standardFrist();
    }

    public function anlegen(): void
    {
        Gate::authorize('admin_apitoken');

        $this->pruefungEinschalten();

        $this->validate($this->regeln(), [], $this->feldnamen());

        // Dritter Parameter von createToken ist der Ablauf - Sanctum weist einen
        // abgelaufenen Token danach von selbst ab.
        $this->frischerToken = auth()->user()
            ->createToken($this->name, ['*'], Carbon::parse($this->expiresAt)->endOfDay())
            ->plainTextToken;

        $this->name = '';
        $this->expiresAt = $this->standardFrist();
    }

    /** Die vorgefüllte Frist: heute plus die Vorgabe aus der Konfiguration. */
    private function standardFrist(): string
    {
        return now()->addDays(config('custom.api_token.gueltigkeit_tage_standard'))->format('Y-m-d');
    }

    public function widerrufen(int $id): void
    {
        Gate::authorize('admin_apitoken');

        // Ueber die Beziehung, nicht ueber die Id allein: Sonst liesse sich mit
        // einer fremden Id der Token eines anderen Benutzers widerrufen.
        auth()->user()->tokens()->whereKey($id)->delete();

        $this->dispatch('hinweis', text: __('Token widerrufen.'));
    }

    public function verbergen(): void
    {
        $this->frischerToken = null;
    }

    public function render()
    {
        return view('livewire.admin-api-token', [
            'tokens' => auth()->user()->tokens()->latest('id')->get(),
        ])->layout('layouts.admin.app');
    }
}
