<x-admin-layout>
    <x-create.main :header="__('Team bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.team.update', $team) }}">
        @method('PATCH')

        @include('admin.team._form')

        {{-- Members may see which customers their team keeps apart. Grouped
             by level - with dozens of customers one badge per chip made a
             restless wall. Each chip leads to the customer's section
             "Zugriff", where the assignment is changed. --}}
        @php
            $byLevel = $team->customers->groupBy(fn ($customer) => $customer->pivot->level);
            $dots = ['full' => 'bg-cerulean-500', 'read' => 'bg-amber-500', 'read_no_secrets' => 'bg-amber-700'];
        @endphp
        <fieldset class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <legend class="flex items-center gap-1.5 px-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                <x-svg.office class="h-4 w-4" />
                {{ __('Kunden des Teams') }}
                @if ($team->customers->isNotEmpty())
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $team->customers->count() }}</span>
                @endif
            </legend>
            @if ($team->customers->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Noch keinem Kunden zugewiesen. Das geschieht beim Kunden unter Admin › Kunden.') }}</p>
            @else
                <div class="space-y-4">
                    @foreach (\App\Support\CustomerAccess::LEVELS as $level)
                        @continue(! $byLevel->has($level))
                        <div>
                            <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <span class="h-2 w-2 rounded-full {{ $dots[$level] }}" aria-hidden="true"></span>
                                {{ \App\Support\CustomerAccess::label($level) }}
                                <span class="font-normal normal-case tracking-normal text-gray-400 dark:text-gray-500">· {{ $byLevel[$level]->count() }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($byLevel[$level] as $customer)
                                    @if (auth()->user()->can('admin_customer') && \App\Support\CustomerAccess::canWrite(auth()->user(), $customer->id))
                                        <a href="{{ route('admin.customer.edit', $customer->id) }}" title="{{ __('Zugriff bei :kunde ändern', ['kunde' => $customer->name]) }}"
                                            class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-sm text-gray-800 transition-colors hover:border-cerulean-300 hover:text-cerulean-700 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-100 dark:hover:border-cerulean-700 dark:hover:text-cerulean-300">{{ $customer->name }}</a>
                                    @else
                                        <span class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-100">{{ $customer->name }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
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
