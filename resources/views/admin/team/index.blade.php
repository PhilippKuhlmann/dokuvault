<x-admin-layout>
    <x-sitetopmenu can="admin_team" :titel="__('Teams')" />

    {{-- What a team does, once, above the list: without it "Team" reads
         like a mere label. --}}
    <p class="mx-3 mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
        {{ __('Ein Kunde mit Team ist nur für dessen Mitglieder sichtbar – auch für Admins. Kunden ohne Team sehen alle. Zugewiesen wird ein Team beim Kunden unter Admin › Kunden. Ein Team ändern dürfen nur seine Mitglieder.') }}
    </p>

    <div class="m-3">
        @error('team')
            <div class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">{{ $message }}</div>
        @enderror

        <x-table.main>
            <x-table.head :labels="['Name', 'Mitglieder', 'Kunden', '']" />
            <x-table.body>
                @forelse ($teams as $team)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700">
                        <td class="px-4 py-3 align-top">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $team->name }}</span>
                            @if ($team->description)
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $team->description }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">
                            {{ $team->members->pluck('name')->join(', ') ?: '—' }}
                        </td>
                        {{-- Only the number: which customers it keeps apart is
                             for its members to know. --}}
                        <td class="px-4 py-3 align-top text-sm text-gray-700 dark:text-gray-300">{{ $team->customers_count }}</td>
                        <td class="px-4 py-3 text-right align-top">
                            @if (in_array($team->id, $mine, true))
                                <x-input.symbolknopf :href="route('admin.team.edit', $team)" :titel="__('Bearbeiten')">
                                    <x-svg.edit class="h-5 w-5" />
                                </x-input.symbolknopf>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500" title="{{ __('Nur Mitglieder dürfen ein Team ändern.') }}">{{ __('kein Mitglied') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Noch keine Teams. Solange kein Kunde einem Team zugewiesen ist, sehen alle Benutzer alle Kunden.') }}
                        </td>
                    </tr>
                @endforelse
            </x-table.body>
        </x-table.main>

        <div class="mt-5 mb-10">{{ $teams->links() }}</div>
    </div>
</x-admin-layout>
