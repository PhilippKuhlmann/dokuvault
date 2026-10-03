<?php

namespace App\Support;

/**
 * A ready-to-run agent script: the file from resources/agents/ with target
 * URL and token filled in.
 *
 * Shared by the download after creating a token (AgentTokenController) and
 * the Windows service, which fetches the current script before every run
 * (AgentController::script). One place, so both always hand out the same.
 */
class AgentSkript
{
    /**
     * @param  array{skript: string}  $variante  One entry of config('custom.agenten.*.varianten')
     */
    public static function rendern(array $variante, string $endpunkt, string $token): string
    {
        $inhalt = file_get_contents(resource_path('agents/'.$variante['skript']));

        // The target URL comes from the agent, not the variant: the PowerShell
        // and the bash version report to the same endpoint.
        return str_replace(
            ['__API_URL__', '__AGENT_TOKEN__'],
            [url('/api/agent/'.$endpunkt), $token],
            $inhalt
        );
    }

    /**
     * Agents the Windows service may run: a PowerShell variant, and no
     * credentials of a foreign system needed at call time (UniFi, vCenter,
     * Graph want parameters the service has no way to supply).
     *
     * @return array<string, array> key => variant
     */
    public static function fuerDienst(): array
    {
        return collect(config('custom.agenten', []))
            ->reject(fn ($agent) => $agent['zugangsdaten'] ?? false)
            ->map(fn ($agent) => collect($agent['varianten'])->first(fn ($v) => str_ends_with($v['skript'], '.ps1')))
            ->filter()
            ->all();
    }
}
