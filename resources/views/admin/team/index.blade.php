<x-admin-layout>

    {{-- Built like Kunden, Rollen and Benutzer: tiles, the shared table row. --}}
    <div class="flex w-full pl-3 pt-3 gap-3">
        <x-adminkachel :label="__('Teams Gesamt')">
            {{ $teamCount }}
        </x-adminkachel>

        <x-adminkachel :label="__('Zuletzt hinzugefügt')" art="name">
            {{ $teamLastAdded?->name ?? '—' }}
        </x-adminkachel>
    </div>

    <x-sitetopmenu can="admin_team" />

<div class="m-3">
    @error('team')
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">{{ $message }}</div>
    @enderror

    <x-table.main>
        <x-table.head :labels="['Name', 'Beschreibung', 'Mitglieder', 'Kunden', '', ]" />

        <x-table.body>

            {{-- The pencil only for members: only they may change a team
                 (TeamController). Of the customers only the number - which
                 ones it keeps apart is for its members to know. --}}
            @foreach ($teams as $team)

                <x-table.datarow
                    :values="[
                        $team->name,
                        $team->description,
                        'liste:Mitglieder' => $team->members->pluck('name')->all(),
                        $team->customers_count,
                    ]"

                    :editUrl="in_array($team->id, $mine, true) ? route('admin.team.edit', $team) : null"
                    can="admin_team"
                />

            @endforeach

        </x-table.body>
    </x-table.main>
    <div class="mt-5 mb-10">
        {{ $teams->links() }}
    </div>

</div>

</x-admin-layout>
