<?php

namespace App\Http\Controllers;

use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Site;
use App\Support\AgentSkript;
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
                $skripte[$schluessel][$i] = AgentSkript::rendern($variante, $agent['endpunkt'], $plain);
            }
        }

        // For the preconfigured service exe (dienstExe): the plain token is
        // gone after this redirect, but the download is a second request.
        // Kept for half an hour and only for this customer - enough to fetch
        // it for a few servers, short enough not to linger.
        session()->put('agentDienst', [
            'customer_id' => $customer->id,
            'token' => $plain,
            'bis' => now()->addMinutes(30)->timestamp,
        ]);

        return redirect(route('agent.index', $customer))
            ->with('newToken', $plain)
            ->with('newTokenName', $token->name ?: ('Token #'.$token->id))
            ->with('agentSkripte', $skripte);
    }

    /** Marker the exe looks for at its own end (agent/windows-service/embedded.go). */
    public const EXE_KENNUNG = 'DVCFG001';

    /**
     * The Windows service exe with URL and token appended - a double click
     * installs it, no command line needed.
     *
     * Appended after the PE image: Windows ignores trailing data, the exe
     * reads its own last bytes (JSON, 4-byte length, marker). Like the
     * script, the file is as confidential as the token in it.
     */
    public function dienstExe(Customer $customer)
    {
        $token = $this->dienstToken($customer);

        $json = json_encode(['url' => url('/'), 'token' => $token], JSON_UNESCAPED_SLASHES);
        $inhalt = file_get_contents(public_path('downloads/dokuvault-agent.exe'))
            .$json.pack('V', strlen($json)).self::EXE_KENNUNG;

        return response($inhalt, 200, [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
            'Content-Disposition' => 'attachment; filename="dokuvault-agent-'.$customer->slug.'.exe"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The Proxmox agent: an installer with URL and token filled in, which
     * sets up a systemd timer on the host (resources/agents/install).
     */
    public function dienstProxmox(Customer $customer)
    {
        $inhalt = AgentSkript::rendernInstaller('proxmox-agent.sh', $this->dienstToken($customer));

        return response($inhalt, 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="dokuvault-agent-proxmox.sh"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The plain token kept after creating it (mitNeuemToken) - only for this
     * customer and only for half an hour; otherwise 410.
     */
    protected function dienstToken(Customer $customer): string
    {
        Gate::authorize('see_hidden');

        $dienst = session('agentDienst');
        abort_unless(
            $dienst && $dienst['customer_id'] === $customer->id && $dienst['bis'] >= now()->timestamp,
            410,
            __('Der Download ist nur kurz nach dem Erzeugen eines Tokens möglich. Bitte einen neuen Token erzeugen.')
        );

        return $dienst['token'];
    }
}
