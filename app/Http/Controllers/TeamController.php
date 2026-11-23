<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin -> Teams: groups of staff users that customers are assigned to
 * (App\Support\CustomerAccess).
 *
 * Only a member may change a team. Otherwise whoever holds admin_team could
 * put themselves into the team of the boss and see the customers it keeps
 * apart - the very thing teams are for. Whoever creates a team is in it.
 * Every change of the members is logged.
 */
class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::withCount(['members', 'customers'])
            ->with('members:id,name')
            ->orderBy('name')
            ->paginate(Setting::seiteAdmin());

        return view('admin.team.index', [
            'teams' => $teams,
            'teamCount' => Team::count(),
            'teamLastAdded' => Team::latest('created_at')->first(),
            'mine' => auth()->user()->teams()->pluck('teams.id')->all(),
        ]);
    }

    public function create()
    {
        return view('admin.team.create', [
            'staff' => $this->staff(),
            'selected' => [auth()->id()],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $team = DB::transaction(function () use ($data) {
            $team = Team::create(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            // The creator is always in it - a team nobody may change is lost.
            $members = collect($data['members'] ?? [])->map(fn ($id) => (int) $id)->push(auth()->id())->unique()->values()->all();
            $team->members()->sync($members);
            $this->logMembers($team, [], $members);

            return $team;
        });

        return redirect()->route('admin.team.index')
            ->with('success', __('Team „:name“ angelegt.', ['name' => $team->name]));
    }

    public function edit(Team $team)
    {
        $this->onlyMember($team);

        return view('admin.team.edit', [
            'team' => $team->load('customers:id,name'),
            'staff' => $this->staff(),
            'selected' => $team->members()->pluck('users.id')->all(),
        ]);
    }

    public function update(Team $team, Request $request)
    {
        $this->onlyMember($team);
        $data = $this->validated($request);
        $members = array_values(array_unique(array_map('intval', $data['members'] ?? [])));

        if ($members === []) {
            return back()->withInput()->withErrors(['members' => __('Ein Team braucht mindestens ein Mitglied – sonst kann es niemand mehr ändern.')]);
        }

        DB::transaction(function () use ($team, $data, $members) {
            $before = $team->members()->pluck('users.id')->all();
            $team->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $team->members()->sync($members);
            $this->logMembers($team, $before, $members);
        });

        return redirect()->route('admin.team.index')
            ->with('success', __('Team „:name“ gespeichert.', ['name' => $team->name]));
    }

    public function destroy(Team $team)
    {
        $this->onlyMember($team);

        // Its customers would be open to everyone the moment it is gone.
        if (DB::table('customer_team')->where('team_id', $team->id)->exists()) {
            return back()->withErrors(['team' => __('Dem Team sind noch Kunden zugewiesen. Erst dort das Team entfernen, dann löschen.')]);
        }

        $team->delete();

        return redirect()->route('admin.team.index')
            ->with('success', __('Team „:name“ gelöscht.', ['name' => $team->name]));
    }

    private function onlyMember(Team $team): void
    {
        abort_unless($team->hasMember(auth()->user()), 403, __('Nur Mitglieder dürfen ein Team ändern.'));
    }

    /** Staff users only: a customer login is kept to its customer anyway. */
    private function staff()
    {
        return User::whereNull('customer_id')->whereNull('deactivated_at')->with('role:id,name')->orderBy('name')->get(['id', 'name', 'username', 'role_id']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'members' => ['array'],
            'members.*' => ['integer', Rule::exists('users', 'id')->whereNull('customer_id')],
        ], [], [
            'name' => __('Name'),
            'members' => __('Mitglieder'),
        ]);
    }

    /** Who came, who went - in the log of the team, by name. */
    private function logMembers(Team $team, array $before, array $after): void
    {
        if (collect($before)->sort()->values()->all() === collect($after)->sort()->values()->all()) {
            return;
        }

        $names = fn (array $ids) => User::whereIn('id', $ids)->orderBy('name')->pluck('name')->join(', ') ?: '—';

        activity()
            ->event('members_changed')
            ->performedOn($team)
            ->causedBy(auth()->user())
            ->withProperties([
                'objekt' => $team->name,
                'old' => [__('Mitglieder') => $names($before)],
                'attributes' => [__('Mitglieder') => $names($after)],
            ])
            ->log('Team-Mitglieder geändert');
    }
}
