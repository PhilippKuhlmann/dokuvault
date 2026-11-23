<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The way back into a team nobody may change any more - its last member
 * left the company, or was deactivated. In the app only a member may change
 * a team (TeamController); on the server whoever holds the console holds
 * everything anyway, so this asks nothing more. Logged like a change in the
 * app.
 */
class TeamAddMember extends Command
{
    protected $signature = 'team:add-member {team : Name or id of the team} {user : Username, e-mail or id}';

    protected $description = 'Adds a staff user to a team - the way in when no member is left';

    public function handle(): int
    {
        $team = Team::whereKey($this->argument('team'))->orWhere('name', $this->argument('team'))->first();
        $user = User::whereNull('customer_id')
            ->where(fn ($q) => $q->whereKey($this->argument('user'))
                ->orWhere('username', $this->argument('user'))
                ->orWhere('email', $this->argument('user')))
            ->first();

        if (! $team || ! $user) {
            $this->error(! $team ? 'Team not found.' : 'Staff user not found (customer logins cannot join a team).');

            return self::FAILURE;
        }

        $team->members()->syncWithoutDetaching([$user->id]);

        activity()
            ->event('members_changed')
            ->performedOn($team)
            ->withProperties(['objekt' => $team->name, 'attributes' => [__('Mitglieder') => '+ '.$user->name.' (Konsole)']])
            ->log('Team-Mitglieder geändert');

        $this->info("{$user->name} is now a member of {$team->name}.");

        return self::SUCCESS;
    }
}
