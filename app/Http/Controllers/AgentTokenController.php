<?php

namespace App\Http\Controllers;

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AgentTokenController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('see_hidden');

        $tokens = $this->getFilteredQuery(AgentToken::class, $customer)
            ->with('site')
            ->latest()
            ->get();

        $sites = Site::where('customer_id', $customer->id)->orderBy('name')->get();

        return view('agent.index', compact('customer', 'tokens', 'sites'));
    }

    public function store(Customer $customer, Request $request)
    {
        Gate::authorize('see_hidden');

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('customer_id', $customer->id)],
            // Pflicht und in der Zukunft: Ein Token ohne Ablauf ist ein
            // Dauerzugang, der auf jedem dokumentierten Rechner liegt.
            'expires_at' => ['required', 'date', 'after:today'],
        ]);

        $site = Site::where('customer_id', $customer->id)->findOrFail($validated['site_id']);

        [$token, $plain] = AgentToken::generateFor(
            $customer,
            $site,
            $validated['name'] ?? null,
            Carbon::parse($validated['expires_at'])->endOfDay(),
        );

        return $this->mitNeuemToken($customer, $token, $plain);
    }

    public function destroy(Customer $customer, AgentToken $agentToken)
    {
        Gate::authorize('see_hidden');
        abort_if($agentToken->customer_id !== $customer->id, 403);

        $agentToken->delete();

        return redirect(route('agent.index', $customer));
    }

    /**
     * Erneuert einen Token: neuer Klartext, neue Frist, der alte Wert ist ab
     * sofort ungültig. Der Weg für einen verbrannten oder ablaufenden Token,
     * ohne die Zuordnung zu Kunde und Standort neu einrichten zu müssen.
     */
    public function erneuern(Customer $customer, AgentToken $agentToken)
    {
        Gate::authorize('see_hidden');
        abort_if($agentToken->customer_id !== $customer->id, 403);

        $frist = now()->addDays(config('custom.agenten_token.gueltigkeit_tage_standard'))->endOfDay();
        $plain = $agentToken->erneuern($frist);

        return $this->mitNeuemToken($customer, $agentToken, $plain);
    }

    /**
     * Weiterleitung nach dem Anlegen oder Erneuern: zeigt den Klartext-Token
     * einmalig und baut die fertigen Skripte dazu.
     *
     * Ein Sessionschluessel fuer alle Agenten statt einer je Agent: sonst
     * muesste jeder neue Agent hier, in der Weiterleitung und in der Ansicht
     * einzeln nachgetragen werden.
     */
    protected function mitNeuemToken(Customer $customer, AgentToken $token, string $plain)
    {
        $skripte = [];
        foreach (config('custom.agenten', []) as $schluessel => $agent) {
            foreach ($agent['varianten'] as $i => $variante) {
                $skripte[$schluessel][$i] = $this->skript($variante, $agent['endpunkt'], $plain);
            }
        }

        return redirect(route('agent.index', $customer))
            ->with('newToken', $plain)
            ->with('newTokenName', $token->name ?: ('Token #'.$token->id))
            ->with('agentSkripte', $skripte);
    }

    /**
     * Liest die Skriptdatei einer Variante und setzt Ziel-URL und Token ein.
     *
     * Die Skripte liegen als Dateien unter resources/agents/ statt als Heredoc
     * im Controller: als Datei sind sie in ihrer eigenen Sprache lesbar, von
     * einem Editor pruefbar und der Controller waechst nicht mit jedem Agenten
     * um hundert Zeilen.
     *
     * Die Ziel-URL kommt vom Agenten, nicht von der Variante: PowerShell- und
     * Bash-Fassung melden an denselben Endpunkt, sie sind zwei Wege zur selben
     * Aufgabe.
     */
    protected function skript(array $variante, string $endpunkt, string $token): string
    {
        $inhalt = file_get_contents(resource_path('agents/'.$variante['skript']));

        return str_replace(
            ['__API_URL__', '__AGENT_TOKEN__'],
            [url('/api/agent/'.$endpunkt), $token],
            $inhalt
        );
    }
}
