<?php

namespace App\Http\Controllers\API\Agent;

use App\Models\AgentInstallation;
use App\Models\Domain;
use App\Models\Service;
use App\Support\AgentScript;
use Illuminate\Http\Request;

/**
 * The installed agent itself, not what it documents: it checks in and gets
 * its roles and interval, reports how a run went, fetches the current script
 * and updates its own program.
 */
class InstallationController extends AgentEndpoint
{
    /**
     * An installed agent reports in every few minutes: which machine, what it
     * detected. The answer is the roles it should run and whether to run now - switched on and off
     * on the agent page, so two DCs can both report as servers while only
     * one reports the domain.
     */
    public function checkin(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(config('custom.dienste', [])))],
            'machine_id' => ['required', 'string', 'max:100'],
            'hostname' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:100'],
            'detected' => ['array'],
            'detected.*' => ['string', 'max:50'],
            'interval' => ['nullable', 'integer', 'min:5', 'max:10080'],
        ]);

        $token = $request->attributes->get('agentToken');
        $machineId = strtolower($data['machine_id']);

        // One token per agent: the first machine to check in owns it. A second
        // one is refused - otherwise locking the token would stop every agent
        // that happened to be installed with it.
        if (! $token->bindTo($machineId)) {
            $owner = AgentInstallation::where('customer_id', $token->customer_id)
                ->where('machine_id', $token->machine_id)->value('hostname');

            abort(409, __('Dieser Token gehört bereits zum Agenten :host. Erzeugen Sie für diesen Agenten einen eigenen Token.', [
                'host' => $owner ?? $token->machine_id,
            ]));
        }

        $installation = AgentInstallation::checkin(
            $token,
            $data['kind'],
            $machineId,
            $data['hostname'],
            $data['domain'] ?? null,
            $data['version'] ?? null,
            $data['detected'] ?? [],
            $data['interval'] ?? null,
        );

        // run: whether to run now (interval due or "Jetzt melden"). Agents
        // from before 26.10.03 do not know it and run on their own timer.
        return response()->json([
            'roles' => $installation->roles,
            'run' => $installation->takeDueRun(),
            'interval' => $installation->intervalMinutes(),
        ]);
    }

    /**
     * The outcome of a run, per role: shown on the agent page and the
     * dashboard. Only for a machine that checked in with this customer.
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'machine_id' => ['required', 'string', 'max:100'],
            'results' => ['required', 'array', 'max:50'],
            'results.*.role' => ['required', 'string', 'max:50'],
            'results.*.ok' => ['required', 'boolean'],
            // Long output is cut to its end by the model, not refused.
            'results.*.message' => ['nullable', 'string', 'max:100000'],
        ]);

        $installation = AgentInstallation::where('customer_id', $request->attributes->get('agentCustomer')->id)
            ->where('machine_id', strtolower($data['machine_id']))
            ->firstOrFail();

        $installation->recordResults($data['results']);

        return response()->json(['status' => 'ok']);
    }

    /**
     * The current PowerShell script of an agent, with URL and token filled in.
     *
     * Called by the Windows service (agent/windows-service) before every run:
     * so script updates reach installed services without anyone downloading
     * anything. The token is the one the service authenticated with - the
     * plain value is only known here because it came in the header.
     *
     * Only agents the service can run unattended (AgentScript::fuerDienst);
     * everything else is a 404, not a hint that it exists.
     */
    public function script(Request $request, string $agent)
    {
        // ?shell=bash for the Proxmox agent; without it PowerShell, as the
        // Windows service has always asked.
        $shell = $request->query('shell') === 'bash' ? 'bash' : 'powershell';
        $variante = AgentScript::forService($shell)[$agent] ?? null;
        abort_unless($variante, 404);

        $token = $request->bearerToken() ?: $request->header('X-Agent-Token');

        return response(
            AgentScript::rendern($variante, config('custom.agenten')[$agent]['endpunkt'], $token),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']
        );
    }

    /**
     * Self-update of an installed agent: asked before every run with the
     * version it has. Same version: 204. Otherwise the current one.
     *
     * windows: the exe, with SHA-256 in a header - the service checks it
     * before replacing itself. proxmox: the installer, rendered with the token
     * the agent authenticated with; run.sh applies it with --update.
     */
    public function update(Request $request, string $service)
    {
        $gemeldet = (string) $request->query('version', '');

        if ($service === 'windows') {
            $aktuell = AgentScript::exeVersion();
            $datei = public_path('downloads/dokuvault-agent.exe');
            abort_unless($aktuell && is_file($datei), 404);

            if ($gemeldet === $aktuell) {
                return response()->noContent();
            }

            return response()->file($datei, [
                'Content-Type' => 'application/vnd.microsoft.portable-executable',
                'X-Agent-Version' => $aktuell,
                'X-Agent-Sha256' => hash_file('sha256', $datei),
                'Cache-Control' => 'no-store',
            ]);
        }

        // proxmox, linux: one installer, rendered with the kind asked for.
        $aktuell = AgentScript::installerVersion('linux-agent.sh');
        if ($gemeldet === $aktuell) {
            return response()->noContent();
        }

        $token = $request->bearerToken() ?: $request->header('X-Agent-Token');

        return response(AgentScript::rendernInstaller('linux-agent.sh', $token, $service), 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'X-Agent-Version' => $aktuell,
            'Cache-Control' => 'no-store',
        ]);
    }
}
