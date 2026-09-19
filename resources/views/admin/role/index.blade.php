<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <x-adminkachel :label="__('Rollen Gesamt')">
            {{ $roleCount }}
        </x-adminkachel>

        <x-adminkachel :label="__('Zuletzt hinzugefügt')" art="name">
            {{ $roleLastAdded->name }}
        </x-adminkachel>





    </div>



    <x-sitetopmenu />

<div class="m-3">
    <x-table.main>
        <x-table.head :labels="['Name', 'Beschreibung', '', ]" />

        <x-table.body>

            @foreach ($roles as $role)

                <x-table.datarow
                    :values="[
                        $role->name,
                        $role->description,
                    ]"

                    editUrl="{{ route('admin.role.edit', $role) }}"
                    can="admin_role"
                />

            @endforeach

        </x-table.body>
    </x-table.main>
    <div class="mt-5 mb-10">
        {{ $roles->links() }}
    </div>

</div>



</x-admin-layout>
