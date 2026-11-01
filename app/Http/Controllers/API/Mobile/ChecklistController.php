<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ADUser;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistOption;
use App\Models\Customer;
use App\Models\NetworkShare;
use App\Support\ChecklistActions;
use App\Support\ChecklistAdScript;
use App\Support\ChecklistBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * On- und Offboarding-Checklisten in der App: sehen, abhaken, freigeben.
 *
 * Drei Rechte wie im Browser: checklist_manage (die IT arbeitet die Listen
 * ab), checklist_request (der Kunde meldet einen neuen Mitarbeiter) und
 * checklist_approve (die Leitung des Kunden gibt heikle Laufwerke frei).
 * Jedes davon darf die Listen sehen.
 *
 * Abgehakt und freigegeben wird in App\Support\Checklistenpflege, derselben
 * Klasse, die die Livewire-Komponente ruft.
 */
class ChecklistController extends Controller
{
    private const SEHEN = ['checklist_manage', 'checklist_request', 'checklist_approve'];

    public function index(Request $request, Customer $customer): JsonResponse
    {
        abort_unless(Gate::any(self::SEHEN), 403);

        $offen = $customer->checklists()->open()
            ->withCount([
                'items',
                'items as erledigt_count' => fn ($abfrage) => $abfrage->whereNotNull('done_at'),
                'items as freigabe_count' => fn ($abfrage) => $abfrage->where('needs_approval', true)->whereNull('approved_at'),
            ])
            ->orderBy('reference_date')
            ->get();

        $fertig = $customer->checklists()->completed()->latest('completed_at')->limit(50)->get();

        return response()->json([
            'open' => $offen->map(fn (Checklist $liste) => [
                ...$this->kurz($liste),
                'items' => $liste->items_count,
                'done' => $liste->erledigt_count,
                // Wie viele noch auf die Leitung warten - daran haengt, ob
                // die IT ueberhaupt weiterkommt.
                'awaiting_approval' => $liste->freigabe_count,
            ])->values(),
            'completed' => $fertig->map(fn (Checklist $liste) => $this->kurz($liste))->values(),
            'may_check' => Gate::allows('checklist_manage'),
            'may_approve' => Gate::allows('checklist_approve'),
        ]);
    }

    public function show(Request $request, Customer $customer, Checklist $checklist): JsonResponse
    {
        abort_unless(Gate::any(self::SEHEN), 403);

        $checklist->load(['items' => fn ($abfrage) => $abfrage->orderBy('sort'), 'items.doneBy', 'items.approvedBy']);

        return response()->json([
            ...$this->kurz($checklist),
            // Leeres als null, nicht als "": Sonst steht in der App eine
            // Zeile ohne Wert - "Notiz" und daneben nichts.
            'note' => $checklist->note ?: null,
            'username' => $checklist->username ?: null,
            'email' => $checklist->email ?: null,
            // Nach Abschnitten, wie die Seite im Browser: Laufwerke,
            // Programme und Zugaenge, Gruppen, E-Mail.
            'sections' => $checklist->items
                ->groupBy('section')
                ->map(fn ($punkte, $abschnitt) => [
                    'title' => __($abschnitt),
                    'items' => $punkte->map(fn (ChecklistItem $punkt) => [
                        'id' => $punkt->id,
                        'title' => $punkt->title,
                        'hint' => $punkt->hint,
                        'assignee' => $punkt->assignee,
                        'done' => $punkt->isDone(),
                        'done_at' => $punkt->done_at?->toIso8601String(),
                        'done_by' => $punkt->doneBy?->name,
                        'needs_approval' => (bool) $punkt->needs_approval,
                        'approved' => $punkt->needs_approval && $punkt->approved_at !== null,
                        'approved_by' => $punkt->approvedBy?->name,
                    ])->values(),
                ])
                ->values(),
            'may_check' => Gate::allows('checklist_manage'),
            'may_approve' => Gate::allows('checklist_approve'),
        ]);
    }

    /**
     * Was eine neue Checkliste braucht: die Laufwerke und Programme, die
     * dieser Kunde im Antrag anbietet, und die AD-Benutzer fuers
     * Offboarding.
     *
     * Zwei Rechte: Melden darf, wer checklist_request oder checklist_manage
     * hat; ein Offboarding anzustossen ist Sache der IT (checklist_manage) -
     * dann ist die Liste der Benutzer leer.
     */
    public function formular(Request $request, Customer $customer): JsonResponse
    {
        abort_unless(Gate::any(['checklist_request', 'checklist_manage']), 403);

        $darfOffboarding = Gate::allows('checklist_manage');

        return response()->json([
            'drives' => ChecklistBuilder::onboardingShares($customer)->map(fn (NetworkShare $freigabe) => [
                'id' => $freigabe->id,
                'name' => $freigabe->name,
                'letter' => $freigabe->drive_letter,
                // Welche Stufen es gibt, haengt an den hinterlegten Gruppen:
                // ohne Schreibgruppe gibt es kein "lesen und schreiben".
                'levels' => collect(ChecklistBuilder::LEVELS)
                    ->filter(fn ($titel, $stufe) => $stufe === 'r' ? $freigabe->ad_group_read_id : $freigabe->ad_group_write_id)
                    ->map(fn ($titel, $stufe) => ['level' => $stufe, 'title' => __($titel)])
                    ->values(),
            ])->values(),
            'apps' => ChecklistBuilder::optionsByKind($customer)
                ->flatMap(fn ($optionen) => $optionen)
                ->map(fn (ChecklistOption $option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'needs_approval' => (bool) $option->needs_approval,
                ])->values(),
            'ad_users' => $darfOffboarding
                ? $customer->adusers()->where('enabled', true)
                    ->orderBy('lastName')->orderBy('firstName')->orderBy('username')
                    ->get()
                    ->map(fn (ADUser $benutzer) => [
                        'id' => $benutzer->id,
                        'name' => trim("{$benutzer->firstName} {$benutzer->lastName}") ?: (string) $benutzer->username,
                        'username' => $benutzer->username,
                    ])->values()
                : collect(),
            'may_offboard' => $darfOffboarding,
        ]);
    }

    /**
     * Ein Onboarding beantragen: Wer kommt, ab wann, welche Laufwerke und
     * Programme.
     *
     * Benutzername und E-Mail rechnet der Server aus Vor- und Nachnamen -
     * dasselbe Schema wie die AD-Benutzer der Dokumentation.
     */
    public function onboarding(Request $request, Customer $customer, ChecklistBuilder $bauer): JsonResponse
    {
        abort_unless(Gate::any(['checklist_request', 'checklist_manage']), 403);

        $geprueft = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'reference_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            // Laufwerk-Id => Stufe. Was der Kunde nicht im Antrag anbietet
            // oder was keine gueltige Stufe hat, wirft der Bauer weg.
            'drives' => ['array'],
            'drives.*' => [Rule::in(array_keys(ChecklistBuilder::LEVELS))],
            'options' => ['array'],
            'options.*' => ['integer'],
        ], [], [
            'first_name' => __('Vorname'),
            'last_name' => __('Nachname'),
            'reference_date' => __('Erster Arbeitstag'),
        ]);

        $liste = $bauer->startOnboarding($customer, $geprueft, $request->user());

        return response()->json(['checklist' => $this->kurz($liste)], 201);
    }

    /** Ein Offboarding anstossen - fuer einen dokumentierten AD-Benutzer. */
    public function offboarding(Request $request, Customer $customer, ChecklistBuilder $bauer): JsonResponse
    {
        Gate::authorize('checklist_manage');

        $geprueft = $request->validate([
            'ad_user_id' => ['required', 'integer'],
            'reference_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'ad_user_id' => __('AD-Benutzer'),
            'reference_date' => __('Letzter Arbeitstag'),
        ]);

        $benutzer = $customer->adusers()->findOrFail($geprueft['ad_user_id']);

        $liste = $bauer->startOffboarding(
            $customer,
            $benutzer,
            $geprueft['reference_date'],
            $request->user(),
            $geprueft['note'] ?? null,
        );

        return response()->json(['checklist' => $this->kurz($liste)], 201);
    }

    /**
     * Einen Punkt abhaken oder den Haken wegnehmen.
     *
     * 409 mit dem Grund, wenn der Punkt noch auf die Freigabe wartet - das
     * ist kein Fehler der App, sondern eine Reihenfolge.
     */
    public function toggle(Request $request, Customer $customer, Checklist $checklist, ChecklistItem $item): JsonResponse
    {
        $grund = ChecklistActions::toggle($item, $request->user());

        if ($grund !== null) {
            return response()->json(['message' => $grund], 409);
        }

        return response()->json([
            'done' => $item->fresh()->isDone(),
            'completed' => $checklist->fresh()->completed_at !== null,
        ]);
    }

    public function approve(Request $request, Customer $customer, Checklist $checklist, ChecklistItem $item): JsonResponse
    {
        ChecklistActions::release($item, $request->user());

        return response()->json(['approved' => true]);
    }

    /**
     * Das PowerShell-Skript fuer den AD-Teil.
     *
     * Laufen muss es auf einem Domaenencontroller, nicht hier - die App
     * holt es und gibt es weiter (Teilen-Blatt). Jeder Abruf gibt einen
     * neuen Schluessel aus; das vorige Skript kann danach nicht mehr
     * zurueckmelden, und der Abruf steht im Protokoll.
     */
    public function adSkript(Request $request, Customer $customer, Checklist $checklist): Response
    {
        Gate::authorize('checklist_manage');

        abort_unless(
            filled($checklist->username),
            422,
            __('Für diese Checkliste ist kein Benutzername hinterlegt.'),
        );

        $schluessel = $checklist->issueScriptToken($request->user());
        $skript = ChecklistAdScript::render($checklist->fresh(['items', 'customer']), $schluessel);

        activity()
            ->event('checklist_script_download')
            ->performedOn($checklist)
            ->causedBy($request->user())
            ->withProperties(['objekt' => $checklist->fullName(), 'attributes' => ['IP' => $request->ip()]])
            ->log('AD-Skript heruntergeladen');

        return response($skript, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.ChecklistAdScript::filename($checklist).'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function kurz(Checklist $liste): array
    {
        return [
            'id' => $liste->id,
            'name' => $liste->fullName(),
            'kind' => $liste->isOnboarding() ? 'onboarding' : 'offboarding',
            'position' => $liste->position ?: null,
            'reference_date' => $liste->reference_date?->toIso8601String(),
            'completed_at' => $liste->completed_at?->toIso8601String(),
        ];
    }
}
