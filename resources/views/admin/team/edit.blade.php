<x-admin-layout>
    <x-create.main :header="__('Team bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.team.update', $team) }}">
        @method('PATCH')

        @include('admin.team._form')

        {{-- Members may see which customers their team keeps apart. --}}
        <div class="mt-6">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Kunden des Teams') }}</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $team->customers->pluck('name')->join(', ') ?: '—' }}</p>
        </div>

        <x-slot:nach>
            <form method="post" action="{{ route('admin.team.destroy', $team) }}" class="border-t border-gray-100 p-5 dark:border-gray-700"
                onsubmit="return confirm(@js(__('Team „:name“ löschen?', ['name' => $team->name])))">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-red-600 hover:text-red-700 dark:text-red-400">{{ __('Team löschen') }}</button>
                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Nur ohne zugewiesene Kunden.') }}</span>
            </form>
        </x-slot>
    </x-create.main>
</x-admin-layout>
