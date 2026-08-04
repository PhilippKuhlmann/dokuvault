<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-cerulean-500 text-center font-CoconPro">
                {{ __('User Gesamt') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $usersCount }}
            </div>
        </div>

        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-cerulean-500 text-center font-CoconPro">
                {{ __('Zuletzt hinzugefügt') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-2xl">
                {{ $userLastAdded->name }}
            </div>
        </div>





    </div>



    <x-sitetopmenu />

<div class="m-3">
    <x-table.main>
        <x-table.head :labels="['Name', 'Benutzername', 'Rolle', 'Kunde', '', ]" />

        <x-table.body>

            @foreach ($users as $user)

                <x-table.datarow
                    :values="[
                        $user->name,
                        $user->username,
                        $user->role?->name ?? '—',
                        $user->customer ? $user->customer->name : ''
                    ]"

                    editUrl="/{{ Request::path() }}/{{ $user->id }}/edit"
                    can="isAdmin"
                />

            @endforeach

        </x-table.body>
    </x-table.main>
    <div class="mt-5 mb-10">
        {{ $users->links() }}
    </div>

</div>



</x-admin-layout>
