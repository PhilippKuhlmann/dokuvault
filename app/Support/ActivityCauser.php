<?php

namespace App\Support;

use App\Models\AgentToken;
use Illuminate\Database\Eloquent\Model;

/**
 * How an activity entry shows its causer.
 *
 * The causer is either a user or an agent token - both have a field 'name',
 * and a token name like "Werkstatt" would look like a person in the column.
 * The agent therefore gets a notation of its own.
 *
 * Stays "System" for everything without a causer: seeders, console commands,
 * scheduled tasks. There really is nobody there.
 */
class ActivityCauser
{
    public static function verursacher(?Model $causer): string
    {
        if (! $causer) {
            return __('System');
        }

        if ($causer instanceof AgentToken) {
            // A token without a label is allowed - then the number stays.
            return __('Agent').' · '.($causer->name ?: 'Token #'.$causer->id);
        }

        return (string) ($causer->name ?? __('System'));
    }
}
