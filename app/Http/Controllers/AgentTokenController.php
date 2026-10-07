<?php

namespace App\Http\Controllers;

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Site;
use App\Support\AgentSkript;
use App\Support\ExeTag;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AgentTokenController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('agent_manage');

        $tokens = $this->getFilteredQuery(AgentToken::class, $customer)
            ->with('site')
            ->latest()
            ->get();

        $sites = Site::where('customer_id', $customer->id)->orderBy('name')->get();

        $installations = AgentInstallation::where('customer_id', $customer->id)
            ->with('agentToken')
            ->orderBy('kind')
            ->orderBy('hostname')
            ->get();

        return view('agent.index', compact('customer', 'tokens', 'sites', 'installations'));
    }

    /**
     * What an installed agent should run - takes effect on its next run.
     * Any role of its kind may be switched on, detected or not: the
     * detection can be wrong, and the agent log then says why it failed.
     */
    public function updateInstallation(Customer $customer, AgentInstallation $agentInstallation, Request $request)
    {
        Gate::authorize('agent_manage');
        abort_if($agentInstallation->customer_id !== $customer->id, 403);

        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => [Rule::in(array_keys(AgentInstallation::availableRoles($agentInstallation->kind)))],
            'interval_minutes' => ['nullable', Rule::in(array_keys(config('custom.agent_intervalle')))],
        ]);

        $agentInstallation->update([
            'roles' => array_values($validated['roles'] ?? []),
            'interval_minutes' => $validated['interval_minutes'] ?? $agentInstallation->interval_minutes,
        ]);

        return redirect(route('agent.index', $customer))
            ->with('success', __('Aufgaben für :name gespeichert – gilt ab dem nächsten Lauf.', ['name' => $agentInstallation->hostname]));
    }

    /**
     * "Jetzt melden": the agent picks it up on its next checkin, within
     * five minutes.
     */
    public function runInstallation(Customer $customer, AgentInstallation $agentInstallation)
    {
        Gate::authorize('agent_manage');
        abort_if($agentInstallation->customer_id !== $customer->id, 403);

        $agentInstallation->forceFill(['run_requested_at' => now()])->save();

        return redirect(route('agent.index', $customer))
            ->with('success', __(':name meldet innerhalb der nächsten fünf Minuten.', ['name' => $agentInstallation->hostname]));
    }

    /**
     * Removes the entry only. A still installed agent reports in again on
     * its next run - uninstalling happens on the machine.
     */
    public function destroyInstallation(Customer $customer, AgentInstallation $agentInstallation)
    {
        Gate::authorize('agent_manage');
        abort_if($agentInstallation->customer_id !== $customer->id, 403);

        $agentInstallation->delete();

        return redirect(route('agent.index', $customer));
    }

    public function store(Customer $customer, Request $request)
    {
        Gate::authorize('agent_manage');

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('customer_id', $customer->id)],
            // Pflicht und in der Zukunft: Ein Token ohne Ablauf ist ein
            // Dauerzugang, der auf jedem dokumentierten Rechner liegt.
            // Not beyond the maximum from Einstellungen -> Agenten.
            'expires_at' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addDays(Setting::agentTokenMaxTage())->toDateString()],
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
        Gate::authorize('agent_manage');
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
        Gate::authorize('agent_manage');
        abort_if($agentToken->customer_id !== $customer->id, 403);

        $frist = now()->addDays(Setting::agentTokenTage())->endOfDay();
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
     * Appended at the end - after the PE image, or inside the signature
     * table of a signed exe (ExeTag); the exe reads its own last bytes
     * (JSON, 4-byte length, marker). Like the script, the file is as
     * confidential as the token in it.
     */
    public function dienstExe(Customer $customer)
    {
        $token = $this->dienstToken($customer);

        $json = json_encode(['url' => url('/'), 'token' => $token], JSON_UNESCAPED_SLASHES);
        // Inside the signature table if the exe is signed (ExeTag).
        $inhalt = ExeTag::anhaengen(
            file_get_contents(public_path('downloads/dokuvault-agent.exe')),
            $json.pack('V', strlen($json)).self::EXE_KENNUNG,
        );

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
        return $this->linuxInstaller($customer, 'proxmox');
    }

    /** The agent for Linux servers (Debian/Ubuntu) - same installer. */
    public function dienstLinux(Customer $customer)
    {
        return $this->linuxInstaller($customer, 'linux');
    }

    /**
     * One installer for both: they differ only in the script they run,
     * set by KIND in the download.
     */
    protected function linuxInstaller(Customer $customer, string $art)
    {
        $inhalt = AgentSkript::rendernInstaller('linux-agent.sh', $this->dienstToken($customer), $art);

        return response($inhalt, 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.config("custom.dienste.$art.datei").'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The plain token kept after creating it (mitNeuemToken) - only for this
     * customer and only for half an hour; otherwise 410.
     */
    protected function dienstToken(Customer $customer): string
    {
        Gate::authorize('agent_manage');

        $dienst = session('agentDienst');
        abort_unless(
            $dienst && $dienst['customer_id'] === $customer->id && $dienst['bis'] >= now()->timestamp,
            410,
            __('Der Download ist nur kurz nach dem Erzeugen eines Tokens möglich. Bitte einen neuen Token erzeugen.')
        );

        return $dienst['token'];
    }
}
