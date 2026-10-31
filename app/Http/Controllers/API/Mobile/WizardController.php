<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DocumentationRun;
use App\Support\Erstaufnahme;
use App\Support\MobilObjekte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Der Erstaufnahme-Assistent in der App: Schritt fuer Schritt durch die
 * Grunddaten eines neuen Kunden.
 *
 * Der Ablauf kommt aus App\Support\Erstaufnahme, derselben Klasse, die die
 * Livewire-Komponente im Browser fuehrt. Das Ausfuellen macht die App mit
 * dem Formular, das der Server ohnehin beschreibt
 * (`customers/{slug}/forms/{typ}`) - der Assistent sagt nur, welcher Typ
 * gerade dran ist und was schon erfasst wurde.
 */
class WizardController extends Controller
{
    public function __construct(private MobilObjekte $objekte) {}

    public function index(Request $request, Customer $customer): JsonResponse
    {
        $ablauf = $this->ablauf($request, $customer);

        return $this->stand($ablauf, $ablauf->durchlauf());
    }

    /** Einen Durchlauf beginnen - oder den offenen weiterfuehren. */
    public function start(Request $request, Customer $customer): JsonResponse
    {
        $ablauf = $this->ablauf($request, $customer);

        return $this->stand($ablauf, $ablauf->beginnen());
    }

    /**
     * Einen Schritt hinter sich lassen.
     *
     * "erledigt" ist das Weiter nach dem Eintragen, "uebersprungen" das
     * Weiter ohne. Wurde in diesem Durchlauf trotzdem etwas angelegt, zaehlt
     * der Schritt als erfasst - nur ein wirklich leer gelassener gilt als
     * uebersprungen.
     */
    public function weiter(Request $request, Customer $customer): JsonResponse
    {
        $geprueft = $request->validate([
            'wie' => ['required', 'in:erledigt,uebersprungen,gruppe'],
        ]);

        $ablauf = $this->ablauf($request, $customer);
        $durchlauf = $ablauf->beginnen();
        $schritt = $ablauf->aktueller($durchlauf);

        if ($schritt === null) {
            return $this->stand($ablauf, $durchlauf);
        }

        if ($geprueft['wie'] === 'gruppe') {
            $ablauf->gruppeUeberspringen($durchlauf);

            return $this->stand($ablauf, $durchlauf->fresh());
        }

        if ($geprueft['wie'] === 'erledigt') {
            $durchlauf->markStepCompleted($schritt['key']);
        } else {
            $ablauf->verlassen($durchlauf, $schritt['key']);
        }

        $ablauf->weiter($durchlauf);

        return $this->stand($ablauf, $durchlauf->fresh());
    }

    /** Einen Schritt zurueck oder direkt zu einem bestimmten springen. */
    public function springen(Request $request, Customer $customer): JsonResponse
    {
        $geprueft = $request->validate([
            'schritt' => ['nullable', 'string'],
        ]);

        $ablauf = $this->ablauf($request, $customer);
        $durchlauf = $ablauf->beginnen();

        if (($geprueft['schritt'] ?? null) === null) {
            $ablauf->zurueck($durchlauf);
        } elseif (! $ablauf->springen($durchlauf, $geprueft['schritt'])) {
            // Ein unbekannter oder gesperrter Schluessel bringt den
            // Durchlauf nicht in einen Zustand, den der Nutzer nicht sehen
            // darf - er ist schlicht kein Schritt.
            abort(404);
        }

        return $this->stand($ablauf, $durchlauf->fresh());
    }

    /** "Als erledigt markieren" - der Durchlauf ist fertig. */
    public function abschliessen(Request $request, Customer $customer): JsonResponse
    {
        $ablauf = $this->ablauf($request, $customer);
        $ablauf->abschliessen();

        return $this->stand($ablauf, null);
    }

    /**
     * Ohne ein einziges _create-Recht aus den Schritten gibt es nichts zu
     * tun - dann ist der Assistent fuer diesen Nutzer gesperrt.
     */
    private function ablauf(Request $request, Customer $customer): Erstaufnahme
    {
        $ablauf = new Erstaufnahme($customer, $request->user());

        abort_if(empty($ablauf->schritte()), 403);

        return $ablauf;
    }

    private function stand(Erstaufnahme $ablauf, ?DocumentationRun $durchlauf): JsonResponse
    {
        $erledigt = $durchlauf?->completed_steps ?? [];
        $uebersprungen = $durchlauf?->skipped_steps ?? [];
        $angelegt = $durchlauf?->created_records ?? [];
        $typen = $this->objekte->typen();

        return response()->json([
            'laeuft' => $durchlauf !== null,
            'aktuell' => $durchlauf?->current_step,
            'fertig' => $durchlauf === null ? null : $durchlauf->completed_at?->toIso8601String(),
            'schritte' => collect($ablauf->schritte())->map(fn ($schritt) => [
                'schluessel' => $schritt['key'],
                'gruppe' => __($schritt['group']),
                'titel' => __($schritt['label']),
                'frage' => __($schritt['question']),
                // Womit die App das Formular holt. null, wo sie den Typ
                // nicht kennt - VLANs etwa haben ihren eigenen Weg.
                'typ' => array_key_exists($schritt['key'], $typen) ? $schritt['key'] : null,
                'erledigt' => in_array($schritt['key'], $erledigt, true),
                'uebersprungen' => in_array($schritt['key'], $uebersprungen, true),
                // Was in diesem Durchlauf hier schon angelegt wurde.
                'angelegt' => count($angelegt[$schritt['key']] ?? []),
            ])->values(),
        ]);
    }
}
