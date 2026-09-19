@use('App\Support\Zeit')
<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <x-adminkachel :label="__('User Gesamt')">
            {{ $usersCount }}
        </x-adminkachel>

        <x-adminkachel :label="__('Zuletzt hinzugefügt')" art="name">
            {{ $userLastAdded->name }}
        </x-adminkachel>
    </div>



    <x-sitetopmenu />

<div class="m-3">
    <x-table.main>
        <x-table.head :labels="['Name', 'Benutzername', 'Rolle', 'Kunde', 'Zweite Stufe', 'Einladung', 'Zuletzt angemeldet', '', ]" />

        <x-table.body>

            {{-- Die zweite Stufe steht als Zeichen in der Spalte, nicht als
                 Wort - siehe x-zweitestufe. Drei Zustaende, nicht zwei:
                 "verlangt" und "eingerichtet" sind verschiedene Dinge, und
                 genau dazwischen sitzt der Benutzer, der noch nichts getan
                 hat. --}}
            @foreach ($users as $user)

                <x-table.datarow
                    :values="[
                        $user->name,
                        $user->username,
                        $user->role?->name ?? '—',
                        $user->customer ? $user->customer->name : '',
                        'zweitestufe' => $user->hatZweiteStufe()
                            ? 'eingerichtet'
                            : ($user->two_factor_required ? 'offen' : null),
                        'einladung' => $user,
                        Zeit::anzeigen($user->last_login_at, 'd.m.Y H:i', __('noch nie')),
                    ]"

                    editUrl="{{ route('admin.user.edit', $user) }}"
                    can="admin_user"
                    {{-- Loeschen ohne Umweg ueber das Bearbeiten-Formular. Sich
                         selbst kann niemand entfernen - sonst stuende man vor
                         einer Anmeldemaske ohne Konto. --}}
                    :delUrl="$user->id === auth()->id() ? '' : route('admin.user.destroy', $user)"
                    canDel="admin_user"
                />

            @endforeach

        </x-table.body>
    </x-table.main>
    <div class="mt-5 mb-10">
        {{ $users->links() }}
    </div>

</div>



</x-admin-layout>
