<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\Controller;

/**
 * What the agents report - the base of every controller below /api/agent.
 *
 * Every device an agent finds or updates is looked up within the site of its
 * token, not the whole customer: a token belongs to one agent at one site and
 * must not overwrite or move devices documented at another one. A device
 * already documented at another site is left alone - the agent documents it
 * at its own site. Kinds without a site (AD, Microsoft 365, backups) stay
 * customer-wide.
 *
 * One controller per kind of agent; what several kinds share lies in
 * Concerns. Customer and site come from the token (AuthenticateAgent) and
 * hang on the request as "agentCustomer" and "agentSite".
 */
abstract class AgentEndpoint extends Controller {}
