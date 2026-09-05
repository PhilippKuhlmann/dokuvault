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

        if (! $token || ! $token->customer || ! $token->site) {
            abort(401, __('Ungültiger Agent-Token.'));
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        /*
         * Der Token ist der Verursacher im Protokoll.
         *
         * Ohne das stand dort "System": Ein Agent hat keinen angemeldeten
         * Benutzer, und wer nachsah, wer die WLANs angelegt hat, fand
         * niemanden. Der Token beantwortet die Frage - er traegt den Namen,
         * den jemand ihm gegeben hat, und haengt an Kunde und Standort.
         */
        CauserResolver::setCauser($token);

        $request->attributes->set('agentToken', $token);
        $request->attributes->set('agentCustomer', $token->customer);
        $request->attributes->set('agentSite', $token->site);

        return $next($request);
    }
}
