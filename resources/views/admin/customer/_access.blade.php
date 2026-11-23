{{--
    Who may see the customer (App\Support\CustomerAccess): no team ticked -
    every staff user, as before; teams ticked - their members only, admins
    included. CustomerTeams refuses a choice that would lock the editor out.
--}}
<fieldset class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
    <legend class="flex items-center gap-1.5 px-1 text-sm font-medium text-gray-700 dark:text-gray-300">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 13a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z" />
            <path d="M8 11V7a4 4 0 1 1 8 0v4" />
        </svg>
        {{ __('Zugriff') }}
    </legend>

    @if ($teams->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Alle Mitarbeiter sehen diesen Kunden. Um ihn auf bestimmte Personen zu beschränken, lege unter Admin › Teams ein Team an.') }}
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Kein Team angehakt: Alle Mitarbeiter sehen den Kunden. Mit Team: nur dessen Mitglieder – auch Admins nicht mehr.') }}
        </p>
        @php ($checked = collect(old('teams', $selectedTeams))->map(fn ($id) => (int) $id)->all())
        <div class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
            @foreach ($teams as $team)
                <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                    <input type="checkbox" name="teams[]" value="{{ $team->id }}" @checked(in_array($team->id, $checked, true))
                        class="rounded border-gray-300 text-cerulean-600 focus:ring-cerulean-500 dark:border-gray-600 dark:bg-gray-700">
                    {{ $team->name }}
                </label>
            @endforeach
        </div>
    @endif

    @error('teams')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</fieldset>
