<?php

namespace App\Http\Middleware;

use App\Models\AgentToken;
use Closure;
use Illuminate\Http\Request;
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

        if (! $token || ! $token->customer || ! $token->site) {
            abort(401, __('Ungültiger Agent-Token.'));
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('agentToken', $token);
        $request->attributes->set('agentCustomer', $token->customer);
        $request->attributes->set('agentSite', $token->site);

        return $next($request);
    }
}
