<x-admin-layout>
    <x-create.main :header="__('Neues Team')" action="{{ route('admin.team.store') }}">
        @include('admin.team._form', ['team' => null])

        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">{{ __('Du bist automatisch Mitglied – ein Team, das niemand ändern darf, wäre verloren.') }}</p>
    </x-create.main>
</x-admin-layout>
