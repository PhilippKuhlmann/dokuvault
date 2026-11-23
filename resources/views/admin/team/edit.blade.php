<x-admin-layout>
    <x-create.main :header="__('Team bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.team.update', $team) }}">
        @method('PATCH')

        @include('admin.team._form')

        {{-- Members may see which customers their team keeps apart - each
             with the level the team has there. Assigned on the customer. --}}
        <fieldset class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <legend class="flex items-center gap-1.5 px-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                <x-svg.office class="h-4 w-4" />
                {{ __('Kunden des Teams') }}
            </legend>
            @if ($team->customers->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Noch keinem Kunden zugewiesen. Das geschieht beim Kunden unter Admin › Kunden.') }}</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($team->customers as $customer)
                        <span class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white py-1 pl-3 pr-1 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-100">
                            {{ $customer->name }}
                            <span @class([
                                'rounded-md px-2 py-0.5 text-xs font-medium text-white',
                                'bg-cerulean-600 dark:bg-cerulean-500' => $customer->pivot->level === 'full',
                                'bg-amber-500 dark:bg-amber-600' => $customer->pivot->level === 'read',
                                'bg-amber-600 dark:bg-amber-700' => $customer->pivot->level === 'read_no_secrets',
                            ])>{{ \App\Support\CustomerAccess::label($customer->pivot->level) }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </fieldset>

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
