<?php

namespace App\Http\Middleware;

use App\Models\AgentToken;
use Closure;
use Illuminate\Http\Request;
use Spatie\Activitylog\Facades\CauserResolver;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    /**
     * Authentifiziert eine Agent-Anfrage über den Bearer-Token (oder X-Agent-Token).
     * Bei Erfolg werden Kunde und Standort des Tokens an der Anfrage hinterlegt.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken() ?: $request->header('X-Agent-Token');

        if (! $plain) {
            abort(401, __('Kein Agent-Token übermittelt.'));
        }

        $token = AgentToken::with(['customer', 'site'])
            ->where('token', AgentToken::hashToken($plain))
            ->first();

        // Expired is treated like invalid - the same message, so from outside
        // nobody can tell whether a token never existed or only ran out.
        if (! $token || ! $token->customer || ! $token->site || $token->istAbgelaufen()) {
            abort(401, __('Ungültiger Agent-Token.'));
        }

        // Locked in the agent list. Its own message: it lands in the agent's
        // log, and whoever reads it there should know it was done on purpose.
        if ($token->isLocked()) {
            abort(401, __('Dieser Agent ist in DokuVault gesperrt.'));
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        /*
         * The token is the causer in the activity log.
         *
         * Without it the log said "System": an agent has no logged-in user,
         * and whoever looked up who created the WLANs found nobody. The token
         * answers that - it carries the name someone gave it and belongs to
         * a customer and a site.
         */
        CauserResolver::setCauser($token);

        $request->attributes->set('agentToken', $token);
        $request->attributes->set('agentCustomer', $token->customer);
        $request->attributes->set('agentSite', $token->site);

        return $next($request);
    }
}
