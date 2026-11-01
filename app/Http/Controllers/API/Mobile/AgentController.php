<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Auto-documentation in the app: which agents run, what they report, and
 * the handles that count on site - "report now", switching tasks,
 * einen Token sperren.
 *
 * Not included: creating tokens and the installation files (.exe, .sh). That
 * is desk work and stands in the browser only - a secret one sees once does
 * not belong on a phone one puts down afterwards.
 */
class AgentController extends Controller
{
    /** How many installations at most - the browser pages through them. */
    public const HOECHSTENS = 200;

    public function index(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('agent_manage');

        $suche = trim((string) $request->query('suche', ''));

        $installationen = AgentInstallation::where('customer_id', $customer->id)
            ->with('agentToken')
            ->when($suche !== '', fn ($abfrage) => $abfrage->whereEnthaelt(['hostname', 'domain'], $suche))
            ->orderBy('kind')
            ->orderBy('hostname')
            ->limit(self::HOECHSTENS + 1)
            ->get();

        $token = AgentToken::where('customer_id', $customer->id)->with('site')->latest()->get();

        return response()->json([
            'agenten' => $installationen->take(self::HOECHSTENS)
                ->map(fn (AgentInstallation $agent) => $this->agent($agent))
                ->values(),
            'weitere' => $installationen->count() > self::HOECHSTENS,
            'token' => $token->map(fn (AgentToken $eines) => [
                'id' => $eines->id,
                'name' => $eines->name ?: 'Token #'.$eines->id,
                'standort' => $eines->site?->name,
                'gesperrt' => $eines->locked_at !== null,
                'laeuft_ab' => $eines->expires_at?->toIso8601String(),
                'abgelaufen' => $eines->isExpired(),
            ])->values(),
            // The intervals the server allows - the app offers only these,
            // or a 422 would come back.
            'takte' => collect(config('custom.agent_intervalle'))
                ->map(fn ($beschriftung, $minuten) => ['minuten' => (int) $minuten, 'titel' => __($beschriftung)])
                ->values(),
        ]);
    }

    /**
     * What this agent should do - applies from its next run.
     *
     * Every task of its kind can be switched on, detected or not: detection
     * can be wrong, and the agent log then says so.
     * woran es scheiterte.
     */
    public function update(Request $request, Customer $customer, AgentInstallation $agentInstallation): JsonResponse
    {
        Gate::authorize('agent_manage');

        $geprueft = $request->validate([
            'roles' => ['array'],
            'roles.*' => [Rule::in(array_keys(AgentInstallation::availableRoles($agentInstallation->kind)))],
            'interval_minutes' => ['nullable', Rule::in(array_keys(config('custom.agent_intervalle')))],
        ]);

        $agentInstallation->update([
            'roles' => array_values($geprueft['roles'] ?? []),
            'interval_minutes' => $geprueft['interval_minutes'] ?? $agentInstallation->interval_minutes,
        ]);

        return response()->json(['agent' => $this->agent($agentInstallation->fresh('agentToken'))]);
    }

    /** "Report now": the agent picks it up on its next check-in. */
    public function run(Request $request, Customer $customer, AgentInstallation $agentInstallation): JsonResponse
    {
        Gate::authorize('agent_manage');

        $agentInstallation->forceFill(['run_requested_at' => now()])->save();

        return response()->json(['agent' => $this->agent($agentInstallation->fresh('agentToken'))]);
    }

    public function lock(Request $request, Customer $customer, AgentToken $agentToken): JsonResponse
    {
        return $this->setLocked($request, $agentToken, true);
    }

    public function unlock(Request $request, Customer $customer, AgentToken $agentToken): JsonResponse
    {
        return $this->setLocked($request, $agentToken, false);
    }

    private function setLocked(Request $request, AgentToken $token, bool $gesperrt): JsonResponse
    {
        Gate::authorize('agent_manage');

        $token->forceFill(['locked_at' => $gesperrt ? now() : null])->save();

        activity()
            ->event($gesperrt ? 'agent_locked' : 'agent_unlocked')
            ->performedOn($token->customer)
            ->causedBy($request->user())
            ->withProperties(['objekt' => $token->name ?: 'Token #'.$token->id])
            ->log($gesperrt ? 'Agent gesperrt' : 'Agent entsperrt');

        return response()->json(['gesperrt' => $gesperrt]);
    }

    /** @return array<string, mixed> */
    private function agent(AgentInstallation $agent): array
    {
        $moegliche = AgentInstallation::availableRoles($agent->kind);

        return [
            'id' => $agent->id,
            'hostname' => $agent->hostname,
            'domaene' => $agent->domain,
            'art' => $agent->kind,
            'version' => $agent->version,
            'zuletzt_gemeldet' => $agent->last_seen_at?->toIso8601String(),
            // No longer reporting - the same question as on the dashboard.
            'stumm' => $agent->isStale(),
            'takt_minuten' => $agent->intervalMinutes(),
            'laufen_angefordert' => $agent->run_requested_at !== null,
            'aufgaben' => collect($moegliche)->map(fn ($beschriftung, $rolle) => [
                'rolle' => $rolle,
                'titel' => __($beschriftung),
                'an' => in_array($rolle, $agent->roles ?? [], true),
                'erkannt' => in_array($rolle, $agent->detected ?? [], true),
                'fehlgeschlagen' => in_array($rolle, $agent->failedRoles(), true),
                'meldung' => $agent->resultFor($rolle)['message'] ?? null,
            ])->values(),
            'token' => $agent->agentToken?->name,
        ];
    }
}
