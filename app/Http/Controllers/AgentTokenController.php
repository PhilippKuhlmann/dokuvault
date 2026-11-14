<?php

namespace App\Http\Controllers;

use App\Models\AgentInstallation;
use App\Models\AgentToken;
use App\Models\Customer;
use App\Models\NetworkScanFinding;
use App\Models\Setting;
use App\Models\Site;
use App\Support\AgentScript;
use App\Support\ExeTag;
use App\Support\LocalTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AgentTokenController extends Controller
{
    public function index(Customer $customer, Request $request)
    {
        Gate::authorize('agent_manage');

        $tokens = $this->getFilteredQuery(AgentToken::class, $customer)
            ->with('site')
            ->latest()
            ->get();

        $sites = Site::where('customer_id', $customer->id)->orderBy('name')->get();

        // Paged and searchable: every row carries its own form, interval
        // select and delete dialog - about 17 KB of HTML. With 2000 agents the
        // page took 2.4 s and was 35 MB.
        $suche = trim((string) $request->query('suche', ''));
        $installations = AgentInstallation::where('customer_id', $customer->id)
            ->with('agentToken')
            ->when($suche !== '', fn ($q) => $q->whereEnthaelt(['hostname', 'domain'], $suche))
            ->orderBy('kind')
            ->orderBy('hostname')
            ->paginate(Setting::seiteListe(), ['*'], 'agenten')
            ->withQueryString()
            ->fragment('installierte-agenten');

        $agentenGesamt = AgentInstallation::where('customer_id', $customer->id)->count();

        // Which agent each token belongs to (one token per agent).
        $boundHosts = AgentInstallation::where('customer_id', $customer->id)
            ->whereIn('machine_id', $tokens->pluck('machine_id')->filter())
            ->pluck('hostname', 'machine_id');

        // What the network scan found, and what was ignored.
        $funde = NetworkScanFinding::forCustomer($customer)
            ->with(['network', 'agentInstallation.agentToken'])
            ->orderBy('type')
            ->orderBy('ip')
            ->get();
        $neuGefunden = $funde->where('status', 'new')->values();
        $ignoriert = $funde->where('status', 'ignored')->values();
        $scanNetze = AgentInstallation::scannableNetworks($customer->id);
        $scanAktiv = AgentInstallation::where('customer_id', $customer->id)->get()->contains(fn ($a) => $a->runsScan());

        return view('agent.index', compact('customer', 'tokens', 'sites', 'installations', 'suche', 'agentenGesamt', 'boundHosts', 'neuGefunden', 'ignoriert', 'scanNetze', 'scanAktiv'));
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
            // Network scan: only networks of this customer the scan can take.
            'scan_network_ids' => ['array'],
            'scan_network_ids.*' => ['integer', Rule::in(AgentInstallation::scannableNetworks($customer->id)->pluck('id')->all())],
            'scan_communities' => ['nullable', 'string', 'max:1000'],
        ]);

        // Before anything is saved: a refused list must not leave the roles
        // changed and the scan settings not.
        $communities = collect(preg_split('/[\s,;]+/', (string) ($validated['scan_communities'] ?? '')))
            ->filter(fn ($c) => $c !== '')
            ->unique()
            ->values();

        if ($communities->count() > AgentInstallation::SCAN_MAX_COMMUNITIES
            || $communities->contains(fn ($c) => mb_strlen($c) > 64)) {
            return back()->withInput()->withErrors([
                'scan_communities' => __('Höchstens :anzahl Communities mit je bis zu 64 Zeichen.', ['anzahl' => AgentInstallation::SCAN_MAX_COMMUNITIES]),
            ]);
        }

        $agentInstallation->fill([
            'roles' => array_values($validated['roles'] ?? []),
            'interval_minutes' => $validated['interval_minutes'] ?? $agentInstallation->interval_minutes,
        ]);

        // Only the Windows form carries the scan settings: saving another
        // agent, or from the app, must not wipe them.
        if ($request->boolean('scan_settings')) {
            $agentInstallation->fill([
                'scan_network_ids' => array_values(array_map('intval', $validated['scan_network_ids'] ?? [])),
                'scan_communities' => $communities->all() ?: null,
            ]);
        }

        $agentInstallation->save();

        return $this->toList($customer)
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

        return $this->toList($customer)
            ->with('success', __(':name meldet innerhalb der nächsten fünf Minuten.', ['name' => $agentInstallation->hostname]));
    }

    /**
     * "Jetzt scannen": handed out with the next checkin, within five
     * minutes - without waiting for the daily scan.
     */
    public function scanInstallation(Customer $customer, AgentInstallation $agentInstallation)
    {
        Gate::authorize('agent_manage');
        abort_if($agentInstallation->customer_id !== $customer->id, 403);
        abort_unless($agentInstallation->runsScan(), 422);

        $agentInstallation->forceFill(['scan_requested_at' => now()])->save();

        return $this->toList($customer)
            ->with('success', __(':name scannt innerhalb der nächsten fünf Minuten.', ['name' => $agentInstallation->hostname]));
    }

    /**
     * Removes the entry only. A still installed agent reports in again on
     * its next run - uninstalling happens on the machine.
     */
    public function destroyInstallation(Customer $customer, AgentInstallation $agentInstallation)
    {
        Gate::authorize('agent_manage');
        abort_if($agentInstallation->customer_id !== $customer->id, 403);

        // Its token becomes free again - for a reinstall on another machine.
        AgentToken::whereKey($agentInstallation->agent_token_id)
            ->where('machine_id', $agentInstallation->machine_id)
            ->update(['machine_id' => null]);

        $agentInstallation->delete();

        return $this->toList($customer);
    }

    /**
     * Back to the agent list as it was - same page, same search. With paging
     * a save on page 3 otherwise landed on page 1. Only to the agent page of
     * this customer: the previous URL comes from the browser.
     */
    private function toList(Customer $customer)
    {
        $liste = route('agent.index', $customer);
        $vorher = url()->previous();

        $ziel = str_starts_with($vorher, $liste) ? strtok($vorher, '#') : $liste;

        return redirect($ziel.'#installierte-agenten');
    }

    public function store(Customer $customer, Request $request)
    {
        Gate::authorize('agent_manage');

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('customer_id', $customer->id)],
            // Required and in the future: a token without an expiry is
            // permanent access lying on every documented machine.
            // Not beyond the maximum from Einstellungen -> Agenten.
            'expires_at' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addDays(Setting::agentTokenMaxDays())->toDateString()],
        ]);

        $site = Site::where('customer_id', $customer->id)->findOrFail($validated['site_id']);

        [$token, $plain] = AgentToken::generateFor(
            $customer,
            $site,
            $validated['name'] ?? null,
            LocalTime::endOfDay($validated['expires_at']),
        );

        return $this->withNewToken($customer, $token, $plain);
    }

    /**
     * Lock an agent: it gets a 401 from its next request on and documents
     * nothing until unlocked. Unlike revoking, nothing has to be reinstalled.
     */
    public function lock(Customer $customer, AgentToken $agentToken)
    {
        return $this->setLocked($customer, $agentToken, true);
    }

    public function unlock(Customer $customer, AgentToken $agentToken)
    {
        return $this->setLocked($customer, $agentToken, false);
    }

    private function setLocked(Customer $customer, AgentToken $agentToken, bool $locked)
    {
        Gate::authorize('agent_manage');
        abort_if($agentToken->customer_id !== $customer->id, 403);

        $agentToken->forceFill(['locked_at' => $locked ? now() : null])->save();

        activity()
            ->event($locked ? 'agent_locked' : 'agent_unlocked')
            ->performedOn($customer)
            ->causedBy(auth()->user())
            ->withProperties(['objekt' => $agentToken->name ?: 'Token #'.$agentToken->id])
            ->log($locked ? 'Agent gesperrt' : 'Agent entsperrt');

        return $this->toList($customer);
    }

    public function destroy(Customer $customer, AgentToken $agentToken)
    {
        Gate::authorize('agent_manage');
        abort_if($agentToken->customer_id !== $customer->id, 403);

        $agentToken->delete();

        return redirect(route('agent.index', $customer));
    }

    /**
     * Renews a token: new plain text, new deadline, the old value invalid
     * from now on. The way for a burnt or expiring token, without having to
     * set up the assignment to customer and site again.
     */
    public function renew(Customer $customer, AgentToken $agentToken)
    {
        Gate::authorize('agent_manage');
        abort_if($agentToken->customer_id !== $customer->id, 403);

        $frist = LocalTime::endOfDay(now()->addDays(Setting::agentTokenDays()));
        $plain = $agentToken->renew($frist);

        return $this->withNewToken($customer, $agentToken, $plain);
    }

    /**
     * Weiterleitung nach dem Anlegen oder Erneuern: zeigt den Klartext-Token
     * once and builds the finished scripts for it.
     *
     * One session key for all agents instead of one per agent: otherwise
     * every new agent would have to be named here, in the redirect and in the view
     * einzeln nachgetragen werden.
     */
    protected function withNewToken(Customer $customer, AgentToken $token, string $plain)
    {
        $skripte = [];
        foreach (config('custom.agenten', []) as $schluessel => $agent) {
            foreach ($agent['varianten'] as $i => $variante) {
                $skripte[$schluessel][$i] = AgentScript::rendern($variante, $agent['endpunkt'], $plain);
            }
        }

        // For the preconfigured service exe (dienstExe): the plain token is
        // gone after this redirect, but the download is a second request.
        // Kept for half an hour, only for this customer and only until the
        // first download (dienstToken).
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
    public function serviceExe(Customer $customer)
    {
        $token = $this->serviceToken($customer);

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
    public function serviceProxmox(Customer $customer)
    {
        return $this->linuxInstaller($customer, 'proxmox');
    }

    /** The agent for Linux servers (Debian/Ubuntu) - same installer. */
    public function serviceLinux(Customer $customer)
    {
        return $this->linuxInstaller($customer, 'linux');
    }

    /**
     * One installer for both: they differ only in the script they run,
     * set by KIND in the download.
     */
    protected function linuxInstaller(Customer $customer, string $art)
    {
        $inhalt = AgentScript::rendernInstaller('linux-agent.sh', $this->serviceToken($customer), $art);

        return response($inhalt, 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.config("custom.dienste.$art.datei").'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The plain token kept after creating it (mitNeuemToken) - only for this
     * customer, only for half an hour, and only for one download; otherwise
     * 410.
     */
    protected function serviceToken(Customer $customer): string
    {
        Gate::authorize('agent_manage');

        $dienst = session('agentDienst');
        abort_unless(
            $dienst && $dienst['customer_id'] === $customer->id && $dienst['bis'] >= now()->timestamp,
            410,
            __('Der Download ist nur kurz nach dem Erzeugen eines Tokens möglich. Bitte einen neuen Token erzeugen.')
        );

        // Gone with the first download: a token belongs to exactly one agent
        // (machine_id), so a second installer from the same token has no use -
        // and the plain token should not sit in the session any longer than
        // the one download it was kept for.
        session()->forget('agentDienst');

        return $dienst['token'];
    }
}
